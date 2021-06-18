<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Api;

use GuzzleHttp\Exception\ClientException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Store\Exception\StoreApiException;
use Shopware\Core\Framework\Store\Exception\StoreInvalidCredentialsException;
use Shopware\Core\Framework\Store\Services\FirstRunWizardClient;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 * @RouteScope(scopes={"api"})
 */
class FirstRunWizardController extends AbstractStoreController
{
    private FirstRunWizardClient $frwClient;

    private EntityRepositoryInterface $pluginRepo;

    private SystemConfigService $configService;

    public function __construct(
        FirstRunWizardClient $frwClient,
        EntityRepositoryInterface $pluginRepo,
        EntityRepositoryInterface $userRepository,
        SystemConfigService $configService
    ) {
        $this->frwClient = $frwClient;
        $this->pluginRepo = $pluginRepo;
        parent::__construct($userRepository);
        $this->configService = $configService;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/store/frw/start", name="api.custom.store.frw.start", methods={"POST"})
     */
    public function frwStart(Context $context): JsonResponse
    {
        try {
            $this->frwClient->startFrw($context);
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        return new JsonResponse();
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/store/language-plugins", name="api.custom.store.language-plugins", methods={"GET"})
     */
    public function getLanguagePluginList(Request $request, Context $context): JsonResponse
    {
        $language = $request->query->get('language', '');

        /** @var PluginCollection $plugins */
        $plugins = $this->pluginRepo->search(new Criteria(), $context)->getEntities();

        try {
            $languagePlugins = $this->frwClient->getLanguagePlugins($language, $plugins);
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        return new JsonResponse([
            'items' => $languagePlugins,
            'total' => \count($languagePlugins),
        ]);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/store/demo-data-plugins", name="api.custom.store.demo-data-plugins", methods={"GET"})
     */
    public function getDemoDataPluginList(Request $request, Context $context): JsonResponse
    {
        $language = $request->query->get('language', '');

        /** @var PluginCollection $plugins */
        $plugins = $this->pluginRepo->search(new Criteria(), $context)->getEntities();

        try {
            $languagePlugins = $this->frwClient->getDemoDataPlugins($language, $plugins);
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        return new JsonResponse([
            'items' => $languagePlugins,
            'total' => \count($languagePlugins),
        ]);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/store/recommendation-regions", name="api.custom.store.recommendation-regions", methods={"GET"})
     */
    public function getRecommendationRegions(Request $request): JsonResponse
    {
        $language = $request->query->get('language', '');

        try {
            $recommendationRegions = $this->frwClient->getRecommendationRegions($language);
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        return new JsonResponse([
            'items' => $recommendationRegions,
            'total' => \count($recommendationRegions),
        ]);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/store/recommendations", name="api.custom.store.recommendations", methods={"GET"})
     */
    public function getRecommendations(Request $request, Context $context): JsonResponse
    {
        $language = $request->query->get('language', '');
        $region = $request->query->get('region');
        $category = $request->query->get('category');

        /** @var PluginCollection $plugins */
        $plugins = $this->pluginRepo->search(new Criteria(), $context)->getEntities();

        try {
            $recommendations = $this->frwClient->getRecommendations($language, $plugins, $region, $category);
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        return new JsonResponse([
            'items' => $recommendations,
            'total' => \count($recommendations),
        ]);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/store/frw/login", name="api.custom.store.frw.login", methods={"POST"})
     */
    public function frwLogin(RequestDataBag $requestDataBag, QueryDataBag $queryDataBag, Context $context): JsonResponse
    {
        $shopwareId = $requestDataBag->get('shopwareId');
        $password = $requestDataBag->get('password');
        $language = $requestDataBag->get('language') ?? $queryDataBag->get('language', '');

        if ($shopwareId === null || $password === null) {
            throw new StoreInvalidCredentialsException();
        }

        $contextSource = $this->ensureAdminApiSource($context);

        try {
            $accessTokenStruct = $this->frwClient->frwLogin($shopwareId, $password, $language, $contextSource->getUserId());
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        $this->configService->set('core.store.shopwareId', $shopwareId);

        $newStoreToken = $accessTokenStruct->getShopUserToken()->getToken();

        $context->scope(Context::SYSTEM_SCOPE, function ($context) use ($newStoreToken): void {
            $this->userRepository->update([['id' => $context->getSource()->getUserId(), 'storeToken' => $newStoreToken]], $context);
        });

        return new JsonResponse();
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/store/license-domains", name="api.custom.store.license-domains", methods={"GET"})
     */
    public function getDomainList(QueryDataBag $params, Context $context): JsonResponse
    {
        $language = $params->get('language', '');
        $storeToken = $this->getUserStoreToken($context);

        try {
            $domains = $this->frwClient->getLicenseDomains($language, $storeToken);
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        return new JsonResponse([
            'items' => $domains,
            'total' => \count($domains),
        ]);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/store/verify-license-domain", name="api.custom.store.verify-license-domain", methods={"POST"})
     */
    public function verifyDomain(QueryDataBag $params, Context $context): JsonResponse
    {
        $storeToken = $this->getUserStoreToken($context);
        $domain = $params->get('domain') ?? '';
        $language = $params->get('language') ?? '';
        $testEnvironment = $params->getBoolean('testEnvironment');

        try {
            $domainStruct = $this->frwClient->verifyLicenseDomain($domain, $language, $storeToken, $testEnvironment);
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        return new JsonResponse(['data' => $domainStruct]);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/store/frw/finish", name="api.custom.store.frw.finish", methods={"POST"})
     */
    public function frwFinish(QueryDataBag $params, Context $context): JsonResponse
    {
        $language = $params->get('language') ?? '';
        $failed = $params->getBoolean('failed');
        $this->frwClient->finishFrw($failed, $context);

        $source = $context->getSource();
        if (!$source instanceof AdminApiSource || $source->getUserId() === null) {
            throw new \RuntimeException('First run wizard requires a logged in user');
        }

        $userId = $source->getUserId();

        $accessToken = null;

        try {
            $storeToken = $this->getUserStoreToken($context);
            $accessToken = $this->frwClient->upgradeAccessToken($storeToken, $language, $userId);
        } catch (\Exception $e) {
        }

        if ($accessToken !== null) {
            $newStoreToken = $accessToken->getShopUserToken()->getToken();

            $context->scope(Context::SYSTEM_SCOPE, function ($context) use ($userId, $newStoreToken): void {
                $this->userRepository->update([['id' => $userId, 'storeToken' => $newStoreToken]], $context);
            });
        }

        return new JsonResponse();
    }
}
