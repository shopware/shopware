<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Module;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppSecretResolver;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\Feature\AppFeature;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\App\Hmac\QuerySigner;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @phpstan-type AppModule array{name: string, label: array<string, string|null>, modules: list<array{name: string, label: array<string, string>, parent: string|null, source: string|null, position: int}>, mainModule: array{source: string}|null}
 */
#[Package('framework')]
class ModuleLoader
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly EntityRepository $appRepository,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly QuerySigner $querySigner,
        private readonly AppFeatureStorage $storage,
        private readonly AppSecretResolver $secretResolver
    ) {
    }

    /**
     * @return list<AppModule>
     */
    public function loadModules(Context $context): array
    {
        $features = [];
        foreach ($this->storage->forActiveApps(ModuleConfig::class) as $feature) {
            $features[$feature->appId] = $feature;
        }

        if ($features === []) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true), new EqualsAnyFilter('id', array_keys($features)))
            ->addAssociation('translations.language.locale');

        $apps = $this->appRepository->search($criteria, $context)->getEntities();

        return $this->formatPayload($apps, $features, $context);
    }

    /**
     * @param array<string, AppFeature<ModuleConfig>> $features
     *
     * @return list<AppModule>
     */
    private function formatPayload(AppCollection $apps, array $features, Context $context): array
    {
        try {
            $this->shopIdProvider->getShopId();
        } catch (ShopIdChangeSuggestedException) {
            return [];
        }

        $appModules = [];

        foreach ($apps as $app) {
            $feature = $features[$app->getId()];

            // sources are signed with the app secret; without one they cannot be called, so the app is skipped
            $secret = $this->secretResolver->resolve($feature->appName);
            if ($secret === null) {
                continue;
            }

            $modules = $this->formatModules($feature, $secret, $context);
            $mainModule = $this->formatMainModule($feature, $secret, $context);

            if ($modules === [] && $mainModule === null) {
                continue;
            }

            $appModules[] = [
                'name' => $feature->appName,
                'label' => $this->mapTranslatedLabels($app),
                'modules' => $modules,
                'mainModule' => $mainModule,
            ];
        }

        return $appModules;
    }

    /**
     * @param AppFeature<ModuleConfig> $feature
     *
     * @return list<array{name: string, label: array<string, string>, parent: string|null, source: string|null, position: int}>
     */
    private function formatModules(AppFeature $feature, string $secret, Context $context): array
    {
        $modules = [];

        foreach ($feature->config->modules as $module) {
            $modules[] = [
                'name' => $module->name,
                'label' => $module->label->all(),
                'parent' => $module->parent,
                'source' => $module->source !== null ? $this->sign($module->source, $feature, $secret, $context) : null,
                'position' => $module->position,
            ];
        }

        return $modules;
    }

    /**
     * @param AppFeature<ModuleConfig> $feature
     *
     * @return array{source: string}|null
     */
    private function formatMainModule(AppFeature $feature, string $secret, Context $context): ?array
    {
        if ($feature->config->mainModule === null) {
            return null;
        }

        return [
            'source' => $this->sign($feature->config->mainModule->source, $feature, $secret, $context),
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
     * @param AppFeature<ModuleConfig> $feature
     */
    private function sign(string $source, AppFeature $feature, string $secret, Context $context): string
    {
        return (string) $this->querySigner->signUriFor($source, $feature->appName, $feature->appVersion, $secret, $context);
    }
}
