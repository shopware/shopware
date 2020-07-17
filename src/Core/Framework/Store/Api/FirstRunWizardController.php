<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Api;

use GuzzleHttp\Exception\ClientException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Store\Exception\StoreApiException;
use Shopware\Core\Framework\Store\Exception\StoreInvalidCredentialsException;
use Shopware\Core\Framework\Store\Exception\StoreTokenMissingException;
use Shopware\Core\Framework\Store\Services\FirstRunWizardClient;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\User\UserEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class FirstRunWizardController extends AbstractController
{
    /**
     * @var FirstRunWizardClient
     */
    private $frwClient;

    /**
     * @var EntityRepositoryInterface
     */
    private $pluginRepo;

    /**
     * @var EntityRepositoryInterface
     */
    private $userRepository;

    public function __construct(FirstRunWizardClient $frwClient, EntityRepositoryInterface $pluginRepo, EntityRepositoryInterface $userRepository)
    {
        $this->frwClient = $frwClient;
        $this->pluginRepo = $pluginRepo;
        $this->userRepository = $userRepository;
    }

    /**
     * @Route("/api/v{version}/_action/store/frw/start", name="api.custom.store.frw.start", methods={"POST"})
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
     * @Route("/api/v{version}/_action/store/language-plugins", name="api.custom.store.language-plugins", methods={"GET"})
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
            'total' => count($languagePlugins),
        ]);
    }

    /**
     * @Route("/api/v{version}/_action/store/demo-data-plugins", name="api.custom.store.demo-data-plugins", methods={"GET"})
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
            'total' => count($languagePlugins),
        ]);
    }

    /**
     * @Route("/api/v{version}/_action/store/recommendation-regions", name="api.custom.store.recommendation-regions", methods={"GET"})
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
            'total' => count($recommendationRegions),
        ]);
    }

    /**
     * @Route("/api/v{version}/_action/store/recommendations", name="api.custom.store.recommendations", methods={"GET"})
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
            'total' => count($recommendations),
        ]);
    }

    /**
     * @Route("/api/v{version}/_action/store/frw/login", name="api.custom.store.frw.login", methods={"POST"})
     */
    public function frwLogin(RequestDataBag $requestDataBag, QueryDataBag $queryDataBag, Context $context): JsonResponse
    {
        $shopwareId = $requestDataBag->get('shopwareId');
        $password = $requestDataBag->get('password');
        $language = $requestDataBag->get('language') ?? $queryDataBag->get('language', '');

        if ($shopwareId === null || $password === null) {
            throw new StoreInvalidCredentialsException();
        }

        if (!$context->getSource() instanceof AdminApiSource) {
            throw new InvalidContextSourceException(AdminApiSource::class, \get_class($context->getSource()));
        }

        try {
            $accessTokenStruct = $this->frwClient->frwLogin($shopwareId, $password, $language, $context->getSource()->getUserId());
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        $newStoreToken = $accessTokenStruct->getShopUserToken()->getToken();

        $context->scope(Context::SYSTEM_SCOPE, function ($context) use ($newStoreToken): void {
            $this->userRepository->update([['id' => $context->getSource()->getUserId(), 'storeToken' => $newStoreToken]], $context);
        });

        return new JsonResponse();
    }

    /**
     * @Route("/api/v{version}/_action/store/license-domains", name="api.custom.store.license-domains", methods={"GET"})
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
            'total' => count($domains),
        ]);
    }

    /**
     * @Route("/api/v{version}/_action/store/verify-license-domain", name="api.custom.store.verify-license-domain", methods={"POST"})
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
     * @Route("/api/v{version}/_action/store/frw/finish", name="api.custom.store.frw.finish", methods={"POST"})
     */
    public function frwFinish(QueryDataBag $params, Context $context): JsonResponse
    {
        $language = $params->get('language') ?? '';
        $failed = $params->getBoolean('failed');
        $this->frwClient->finishFrw($failed, $context);

        $userId = null;
        $newStoreToken = '';

        try {
            $userId = $context->getSource() instanceof AdminApiSource ? $context->getSource()->getUserId() : null;
            $storeToken = $this->getUserStoreToken($context);
            $accessToken = $this->frwClient->upgradeAccessToken($storeToken, $language);
            $newStoreToken = $accessToken->getShopUserToken()->getToken();
        } catch (\Exception $e) {
        }

        if ($userId) {
            $context->scope(Context::SYSTEM_SCOPE, function ($context) use ($userId, $newStoreToken): void {
                $this->userRepository->update([['id' => $userId, 'storeToken' => $newStoreToken]], $context);
            });
        }

        return new JsonResponse();
    }

    private function getUserStoreToken(Context $context): string
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            throw new InvalidContextSourceException(AdminApiSource::class, \get_class($context->getSource()));
        }

        $userId = $context->getSource()->getUserId();

        /** @var UserEntity|null $user */
        $user = $this->userRepository->search(new Criteria([$userId]), $context)->first();

        if ($user->getStoreToken() === null) {
            throw new StoreTokenMissingException();
        }

        return $user->getStoreToken();
    }
}
