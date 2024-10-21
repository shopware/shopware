<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\Hmac\QuerySigner;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @phpstan-import-type Module from AppEntity
 *
 * @phpstan-type AppModule array{name: string, label: array<string, string|null>, modules: list<Module>, mainModule: array{source: string}|null}
 */
#[Package('core')]
class ModuleLoader
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly EntityRepository $appRepository,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly QuerySigner $querySigner
    ) {
    }

    /**
     * @return list<AppModule>
     */
    public function loadModules(Context $context): array
    {
        $criteria = new Criteria();
        $containsModulesFilter = new NotFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('modules', '[]'),
                new EqualsFilter('mainModule', null),
            ]
        );
        $appActiveFilter = new EqualsFilter('active', true);
        $criteria->addFilter($containsModulesFilter, $appActiveFilter)
            ->addAssociation('translations.language.locale');

        $apps = $this->appRepository->search($criteria, $context)->getEntities();

        return $this->formatPayload($apps, $context);
    }

    /**
     * @return list<AppModule>
     */
    private function formatPayload(AppCollection $apps, Context $context): array
    {
        try {
            $this->shopIdProvider->getShopId();
        } catch (AppUrlChangeDetectedException) {
            return [];
        }

        $appModules = [];

        foreach ($apps as $app) {
            $modules = $this->formatModules($app, $context);
            $mainModule = $this->formatMainModule($app, $context);

            if (empty($modules) && $mainModule === null) {
                continue;
            }

            $appModules[] = [
                'name' => $app->getName(),
                'label' => $this->mapTranslatedLabels($app),
                'modules' => $modules,
                'mainModule' => $mainModule,
            ];
        }

        return $appModules;
    }

    /**
     * @return list<Module>
     */
    private function formatModules(AppEntity $app, Context $context): array
    {
        $modules = [];

        foreach ($app->getModules() as $module) {
            $module['source'] = $this->getModuleUrlWithQuery($app, $module, $context);
            $modules[] = $module;
        }

        return $modules;
    }

    /**
     * @return array{source: string}|null
     */
    private function formatMainModule(AppEntity $app, Context $context): ?array
    {
        if ($app->getMainModule() === null) {
            return null;
        }

        $source = $app->getMainModule()['source'] ?? '';

        return [
            'source' => $this->sign($source, $app, $context),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function mapTranslatedLabels(AppEntity $app): array
    {
        $labels = [];
        $translations = $app->getTranslations();
        if ($translations === null) {
            return $labels;
        }

        foreach ($translations as $translation) {
            $code = $translation->getLanguage()?->getLocale()?->getCode();
            if ($code === null) {
                continue;
            }
            $labels[$code] = $translation->getLabel();
        }

        return $labels;
    }

    /**
     * @param Module $module
     */
    private function getModuleUrlWithQuery(AppEntity $app, array $module, Context $context): ?string
    {
        $registeredSource = $module['source'] ?? null;
        if ($registeredSource === null) {
            return null;
        }

        return $this->sign($registeredSource, $app, $context);
    }

    private function sign(string $source, AppEntity $app, Context $context): string
    {
        return (string) $this->querySigner->signUri($source, $app, $context);
    }
}
