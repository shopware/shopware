<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\App;

use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateCollection;
use Shopware\Core\Framework\App\Feature\AppFeatureConfig;
use Shopware\Core\Framework\App\Feature\AppFeatureDefinition;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Storefront\EntitySeoUrl;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * Maps the manifest `<storefront><entity-seo-url>` elements to `app_feature` rows of type `storefront_entity_seo_url`.
 *
 * @internal
 *
 * @extends AppFeatureDefinition<AppEntitySeoUrlConfig>
 *
 * @phpstan-type EntitySeoUrlPayload array{name: string, routeName: string, hook: string, entityName: string, defaultTemplate: string}
 */
#[Package('inventory')]
final class EntitySeoUrlAppFeatureDefinition extends AppFeatureDefinition
{
    final public const TYPE = 'storefront_entity_seo_url';

    /**
     * @param EntityRepository<SeoUrlTemplateCollection> $seoUrlTemplateRepository
     */
    public function __construct(private readonly EntityRepository $seoUrlTemplateRepository)
    {
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getConfigClass(): string
    {
        return AppEntitySeoUrlConfig::class;
    }

    public function fromApp(Manifest $manifest, Filesystem $appFilesystem, string $defaultLocale): array
    {
        $appName = $manifest->getMetadata()->getName();

        return array_map(
            static fn (EntitySeoUrl $seoUrl): AppEntitySeoUrlConfig => new AppEntitySeoUrlConfig(
                $seoUrl->getName(),
                AppSeoUrlRoute::buildRouteName($appName, $seoUrl->getName()),
                $seoUrl->getHook(),
                $seoUrl->getEntity(),
                $seoUrl->getDefaultTemplate(),
            ),
            $manifest->getStorefront()?->getEntitySeoUrls() ?? []
        );
    }

    /**
     * @param list<AppEntitySeoUrlConfig> $configs
     */
    public function persisted(array $configs, AppPersistContext $context): void
    {
        foreach ($configs as $config) {
            $this->seedDefaultTemplate($config, $context->context);
        }
    }

    /**
     * @return EntitySeoUrlPayload
     */
    public function toPayload(AppFeatureConfig $declared, ?AppFeatureConfig $stored): array
    {
        return [
            'name' => $declared->getName(),
            'routeName' => $declared->getRouteName(),
            'hook' => $declared->getHook(),
            'entityName' => $declared->getEntityName(),
            'defaultTemplate' => $declared->getDefaultTemplate(),
        ];
    }

    /**
     * @param EntitySeoUrlPayload $payload
     */
    public function fromPayload(array $payload): AppEntitySeoUrlConfig
    {
        return new AppEntitySeoUrlConfig(
            $payload['name'],
            $payload['routeName'],
            $payload['hook'],
            $payload['entityName'],
            $payload['defaultTemplate'],
        );
    }

    private function seedDefaultTemplate(AppEntitySeoUrlConfig $config, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->setTitle('app-seo-url::default-template');
        $criteria->addFilter(new EqualsFilter('routeName', $config->getRouteName()));
        $criteria->addFilter(new EqualsFilter('salesChannelId', null));

        $existing = $this->seoUrlTemplateRepository->search($criteria, $context)->getEntities()->first();

        if ($existing === null) {
            $this->seoUrlTemplateRepository->create([[
                'routeName' => $config->getRouteName(),
                'entityName' => $config->getEntityName(),
                'template' => $config->getDefaultTemplate(),
                'isValid' => true,
                'isHeadless' => false,
            ]], $context);

            return;
        }

        if ($existing->getEntityName() === $config->getEntityName()) {
            return;
        }

        $this->seoUrlTemplateRepository->update([[
            'id' => $existing->getId(),
            'entityName' => $config->getEntityName(),
            'template' => $config->getDefaultTemplate(),
        ]], $context);
    }
}
