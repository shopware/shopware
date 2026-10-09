<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\App;

use Shopware\Core\Content\Seo\SeoException;
use Shopware\Core\Content\Seo\SeoUrlGenerator;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateCollection;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateEntity;
use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\Feature\AppFeatureConfig;
use Shopware\Core\Framework\App\Feature\AppFeatureDefinition;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Storefront\EntitySeoUrl;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
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
    public function __construct(
        private readonly EntityRepository $seoUrlTemplateRepository,
        private readonly AppSeoUrlClaims $claims,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly SeoUrlGenerator $seoUrlGenerator,
        private readonly AclCriteriaValidator $aclCriteriaValidator,
    ) {
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
    public function validate(array $configs, AppPersistContext $context): void
    {
        if ($configs === []) {
            return;
        }

        $source = new AdminApiSource(null, null);
        $source->setPermissions($context->manifest->getPermissions()?->asParsedPrivileges() ?? []);
        $aclContext = new Context($source);

        $appName = $context->app->getName();
        $hooksOfOtherApps = $this->claims->hooksOfOtherApps($appName);
        $declaredHooks = [];

        foreach ($configs as $config) {
            $this->assertPermitted($config, $aclContext);

            $hook = $config->getHook();

            if (isset($hooksOfOtherApps[$hook])) {
                throw SeoException::appSeoUrlHookAlreadyRegistered($config->getName(), $hook, $hooksOfOtherApps[$hook]);
            }

            if (isset($declaredHooks[$hook])) {
                throw SeoException::appSeoUrlHookAlreadyRegistered($config->getName(), $hook, $appName);
            }

            $declaredHooks[$hook] = true;
        }
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

    private function assertPermitted(AppEntitySeoUrlConfig $config, Context $aclContext): void
    {
        $entityName = $config->getEntityName();

        if (!$this->definitionRegistry->has($entityName)) {
            if (!str_starts_with($entityName, 'ce_') && !str_starts_with($entityName, 'custom_entity_')) {
                throw SeoException::appEntitySeoUrlEntityUnsupported($config->getName(), $entityName);
            }

            if (!$aclContext->isAllowed($entityName . ':read')) {
                throw SeoException::appEntitySeoUrlNotPermitted($config->getName(), $entityName, [$entityName . ':read']);
            }

            return;
        }

        $definition = $this->definitionRegistry->getByEntityName($entityName);

        if ($definition instanceof MappingEntityDefinition || $definition instanceof EntityTranslationDefinition) {
            throw SeoException::appEntitySeoUrlEntityUnsupported($config->getName(), $entityName);
        }

        $criteria = new Criteria();
        $criteria->addAssociations($this->seoUrlGenerator->getAssociations($config->getDefaultTemplate(), $definition));

        $missing = $this->aclCriteriaValidator->validate($entityName, $criteria, $aclContext);

        if ($missing !== []) {
            throw SeoException::appEntitySeoUrlNotPermitted($config->getName(), $entityName, $missing);
        }
    }

    private function seedDefaultTemplate(AppEntitySeoUrlConfig $config, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->setTitle('app-seo-url::default-template');
        $criteria->addFilter(new EqualsFilter('routeName', $config->getRouteName()));

        $templates = $this->seoUrlTemplateRepository->search($criteria, $context)->getEntities();
        $default = $templates->firstWhere(static fn (SeoUrlTemplateEntity $template): bool => $template->getSalesChannelId() === null);

        if ($default !== null && $default->getEntityName() === $config->getEntityName()) {
            return;
        }

        if ($templates->count() > 0) {
            $this->seoUrlTemplateRepository->delete(
                array_values($templates->map(static fn (SeoUrlTemplateEntity $template): array => ['id' => $template->getId()])),
                $context
            );
        }

        $this->seoUrlTemplateRepository->create([[
            'routeName' => $config->getRouteName(),
            'entityName' => $config->getEntityName(),
            'template' => $config->getDefaultTemplate(),
            'isValid' => true,
            'isHeadless' => false,
        ]], $context);
    }
}
