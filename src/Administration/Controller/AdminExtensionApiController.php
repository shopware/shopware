<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Shopware\Administration\Controller\Exception\AppByNameNotFoundException;
use Shopware\Administration\Controller\Exception\MissingAppSecretException;
use Shopware\Administration\Controller\Exception\MissingShopUrlException;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\App\ActionButton\AppAction;
use Shopware\Core\Framework\App\ActionButton\Executor;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal Only to be used by the admin-extension-sdk.
 *
 * @RouteScope(scopes={"api"})
 */
class AdminExtensionApiController extends AbstractController
{
    private Executor $executor;

    private ShopIdProvider $shopIdProvider;

    private EntityRepositoryInterface $appRepository;

    public function __construct(
        Executor $executor,
        ShopIdProvider $shopIdProvider,
        EntityRepositoryInterface $appRepository
    ) {
        $this->executor = $executor;
        $this->shopIdProvider = $shopIdProvider;
        $this->appRepository = $appRepository;
    }

    /**
     * @Route("/api/_action/extension-sdk/run-action", name="api.action.extension-sdk.run-action", methods={"POST"})
     */
    public function runAction(RequestDataBag $requestDataBag, Context $context): Response
    {
        Feature::throwException('FEATURE_NEXT_17950', 'Feature is not active', false);

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

        $action = new AppAction(
            $requestDataBag->get('url'),
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
}
