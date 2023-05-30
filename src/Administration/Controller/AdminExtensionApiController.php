<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Shopware\Administration\Controller\Exception\AppByNameNotFoundException;
use Shopware\Administration\Controller\Exception\MissingAppSecretException;
use Shopware\Administration\Controller\Exception\MissingShopUrlException;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\App\ActionButton\AppAction;
use Shopware\Core\Framework\App\ActionButton\Executor;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Hmac\QuerySigner;
use Shopware\Core\Framework\App\Manifest\Exception\UnallowedHostException;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal Only to be used by the admin-extension-sdk.
 */
#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('administration')]
class AdminExtensionApiController extends AbstractController
{
    public function __construct(
        private readonly Executor $executor,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly EntityRepository $appRepository,
        private readonly QuerySigner $querySigner
    ) {
    }

    #[Route(path: '/api/_action/extension-sdk/run-action', name: 'api.action.extension-sdk.run-action', methods: ['POST'])]
    public function runAction(RequestDataBag $requestDataBag, Context $context): Response
    {
        $appName = $requestDataBag->get('appName');
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('name', $appName)
        );

        /** @var AppEntity|null $app */
        $app = $this->appRepository->search($criteria, $context)->first();
        if ($app === null) {
            throw new AppByNameNotFoundException($appName);
        }

        $shopUrl = EnvironmentHelper::getVariable('APP_URL');
        if (!\is_string($shopUrl)) {
            throw new MissingShopUrlException();
        }

        $appSecret = $app->getAppSecret();
        if ($appSecret === null) {
            throw new MissingAppSecretException();
        }

        $targetUrl = $requestDataBag->get('url');
        $targetHost = \parse_url((string) $targetUrl, \PHP_URL_HOST);
        $allowedHosts = $app->getAllowedHosts() ?? [];
        if (!$targetHost || !\in_array($targetHost, $allowedHosts, true)) {
            throw new UnallowedHostException($targetUrl, $allowedHosts, $app->getName());
        }

        $action = new AppAction(
            $targetUrl,
            $shopUrl,
            $app->getVersion(),
            $requestDataBag->get('entity'),
            $requestDataBag->get('action'),
            $requestDataBag->get('ids')->all(),
            $appSecret,
            $this->shopIdProvider->getShopId(),
            Uuid::randomHex()
        );

        return $this->executor->execute($action, $context);
    }

    #[Route(path: '/api/_action/extension-sdk/sign-uri', name: 'api.action.extension-sdk.sign-uri', methods: ['POST'])]
    public function signUri(RequestDataBag $requestDataBag, Context $context): Response
    {
        $appName = $requestDataBag->get('appName');
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('name', $appName)
        );

        /** @var AppEntity|null $app */
        $app = $this->appRepository->search($criteria, $context)->first();
        if ($app === null) {
            throw new AppByNameNotFoundException($appName);
        }

        $secret = $app->getAppSecret();
        if ($secret === null) {
            throw new MissingAppSecretException();
        }

        $uri = $this->querySigner->signUri($requestDataBag->get('uri'), $secret, $context)->__toString();

        return new JsonResponse([
            'uri' => $uri,
        ]);
    }
}
