<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Api;

use GuzzleHttp\Exception\ClientException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Store\Exception\StoreApiException;
use Shopware\Core\Framework\Store\Exception\StoreInvalidCredentialsException;
use Shopware\Core\Framework\Store\Services\FirstRunWizardService;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 */
#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('merchant-services')]
class FirstRunWizardController extends AbstractController
{
    public function __construct(
        private readonly FirstRunWizardService $frwService,
        private readonly EntityRepository $pluginRepo
    ) {
    }

    #[Route(path: '/api/_action/store/frw/start', name: 'api.custom.store.frw.start', methods: ['POST'])]
    public function frwStart(Context $context): JsonResponse
    {
        try {
            $this->frwService->startFrw($context);
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        return new JsonResponse();
    }

    #[Route(path: '/api/_action/store/language-plugins', name: 'api.custom.store.language-plugins', methods: ['GET'])]
    public function getLanguagePluginList(Context $context): JsonResponse
    {
        /** @var PluginCollection $plugins */
        $plugins = $this->pluginRepo->search(new Criteria(), $context)->getEntities();

        try {
            $languagePlugins = $this->frwService->getLanguagePlugins($plugins, $context);
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        return new JsonResponse([
            'items' => $languagePlugins,
            'total' => \count($languagePlugins),
        ]);
    }

    #[Route(path: '/api/_action/store/demo-data-plugins', name: 'api.custom.store.demo-data-plugins', methods: ['GET'])]
    public function getDemoDataPluginList(Context $context): JsonResponse
    {
        /** @var PluginCollection $plugins */
        $plugins = $this->pluginRepo->search(new Criteria(), $context)->getEntities();

        try {
            $languagePlugins = $this->frwService->getDemoDataPlugins($plugins, $context);
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        return new JsonResponse([
            'items' => $languagePlugins,
            'total' => \count($languagePlugins),
        ]);
    }

    #[Route(path: '/api/_action/store/recommendation-regions', name: 'api.custom.store.recommendation-regions', methods: ['GET'])]
    public function getRecommendationRegions(Context $context): JsonResponse
    {
        try {
            $recommendationRegions = $this->frwService->getRecommendationRegions($context);
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        return new JsonResponse([
            'items' => $recommendationRegions,
            'total' => \count($recommendationRegions),
        ]);
    }

    #[Route(path: '/api/_action/store/recommendations', name: 'api.custom.store.recommendations', methods: ['GET'])]
    public function getRecommendations(Request $request, Context $context): JsonResponse
    {
        $region = $request->query->has('region') ? (string) $request->query->get('region') : null;
        $category = $request->query->has('category') ? (string) $request->query->get('category') : null;

        /** @var PluginCollection $plugins */
        $plugins = $this->pluginRepo->search(new Criteria(), $context)->getEntities();

        try {
            $recommendations = $this->frwService->getRecommendations($plugins, $region, $category, $context);
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        return new JsonResponse([
            'items' => $recommendations,
            'total' => \count($recommendations),
        ]);
    }

    #[Route(path: '/api/_action/store/frw/login', name: 'api.custom.store.frw.login', methods: ['POST'])]
    public function frwLogin(RequestDataBag $requestDataBag, Context $context): JsonResponse
    {
        $shopwareId = $requestDataBag->get('shopwareId');
        $password = $requestDataBag->get('password');

        if ($shopwareId === null || $password === null) {
            throw new StoreInvalidCredentialsException();
        }

        try {
            $this->frwService->frwLogin($shopwareId, $password, $context);
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        return new JsonResponse();
    }

    #[Route(path: '/api/_action/store/license-domains', name: 'api.custom.store.license-domains', methods: ['GET'])]
    public function getDomainList(Context $context): JsonResponse
    {
        try {
            $domains = $this->frwService->getLicenseDomains($context);
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        return new JsonResponse([
            'items' => $domains,
            'total' => \count($domains),
        ]);
    }

    #[Route(path: '/api/_action/store/verify-license-domain', name: 'api.custom.store.verify-license-domain', methods: ['POST'])]
    public function verifyDomain(QueryDataBag $params, Context $context): JsonResponse
    {
        $domain = $params->get('domain') ?? '';
        $testEnvironment = $params->getBoolean('testEnvironment');

        try {
            $domainStruct = $this->frwService->verifyLicenseDomain($domain, $context, $testEnvironment);
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        return new JsonResponse(['data' => $domainStruct]);
    }

    #[Route(path: '/api/_action/store/frw/finish', name: 'api.custom.store.frw.finish', methods: ['POST'])]
    public function frwFinish(QueryDataBag $params, Context $context): JsonResponse
    {
        $failed = $params->getBoolean('failed');
        $this->frwService->finishFrw($failed, $context);

        try {
            $this->frwService->upgradeAccessToken($context);
        } catch (\Exception) {
        }

        return new JsonResponse();
    }
}
