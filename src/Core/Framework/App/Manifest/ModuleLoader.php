<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest;

use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\UriInterface;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppLocaleProvider;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Hmac\RequestSigner;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class ModuleLoader
{
    private EntityRepositoryInterface $appRepository;

    private string $shopUrl;

    private ShopIdProvider $shopIdProvider;

    private string $shopwareVersion;

    private AppLocaleProvider $localeProvider;

    public function __construct(
        EntityRepositoryInterface $appRepository,
        string $shopUrl,
        ShopIdProvider $shopIdProvider,
        string $shopwareVersion,
        AppLocaleProvider $localeProvider
    ) {
        $this->appRepository = $appRepository;
        $this->shopUrl = $shopUrl;
        $this->shopIdProvider = $shopIdProvider;
        $this->shopwareVersion = $shopwareVersion;
        $this->localeProvider = $localeProvider;
    }

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

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search($criteria, $context)->getEntities();

        return $this->formatPayload($apps, $context);
    }

    private function formatPayload(AppCollection $apps, Context $context): array
    {
        try {
            $shopId = $this->shopIdProvider->getShopId();
        } catch (AppUrlChangeDetectedException $e) {
            return [];
        }

        $appModules = [];

        foreach ($apps as $app) {
            $modules = $this->formatModules($shopId, $app, $context);
            $mainModule = $this->formatMainModule($shopId, $app, $context);

            if (empty($modules) && !$mainModule) {
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

    private function formatModules(string $shopId, AppEntity $app, Context $context): array
    {
        $modules = [];

        foreach ($app->getModules() as $module) {
            $module['source'] = $this->getModuleUrlWithQuery($app, $shopId, $module, $context);
            $modules[] = $module;
        }

        return $modules;
    }

    private function formatMainModule(string $shopId, AppEntity $app, Context $context): ?array
    {
        if ($app->getMainModule() === null) {
            return null;
        }

        return [
            'source' => $this->getUrlWithQuery($app, $shopId, $app->getMainModule()['source'], $context),
        ];
    }

    private function mapTranslatedLabels(AppEntity $app): array
    {
        $labels = [];

        foreach ($app->getTranslations() as $translation) {
            $labels[$translation->getLanguage()->getLocale()->getCode()] = $translation->getLabel();
        }

        return $labels;
    }

    private function getModuleUrlWithQuery(AppEntity $app, string $shopId, array $module, Context $context): ?string
    {
        $registeredSource = $module['source'] ?? null;

        if ($registeredSource === null) {
            return null;
        }

        return $this->getUrlWithQuery($app, $shopId, $registeredSource, $context);
    }

    private function getUrlWithQuery(AppEntity $app, string $shopId, string $source, Context $context): string
    {
        $uri = $this->generateQueryString($source, $shopId, $context);

        /** @var string $secret */
        $secret = $app->getAppSecret();
        $signature = (new RequestSigner())->signPayload($uri->getQuery(), $secret);

        return (string) Uri::withQueryValue(
            $uri,
            RequestSigner::SHOPWARE_SHOP_SIGNATURE,
            $signature
        );
    }

    private function generateQueryString(string $uri, string $shopId, Context $context): UriInterface
    {
        $date = new \DateTime();
        $uri = new Uri($uri);

        return Uri::withQueryValues($uri, [
            'shop-id' => $shopId,
            'shop-url' => $this->shopUrl,
            'timestamp' => $date->getTimestamp(),
            'sw-version' => $this->shopwareVersion,
            AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE => $context->getLanguageId(),
            AuthMiddleware::SHOPWARE_USER_LANGUAGE => $this->localeProvider->getLocaleFromContext($context),
        ]);
    }
}
