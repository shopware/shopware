<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Doctrine\DBAL\Connection;
use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"store-api"})
 */
class LogoutRoute extends AbstractLogoutRoute
{
    /**
     * @var SalesChannelContextPersister
     */
    private $contextPersister;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var SystemConfigService
     */
    private $systemConfig;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(
        SalesChannelContextPersister $contextPersister,
        EventDispatcherInterface $eventDispatcher,
        SystemConfigService $systemConfig,
        CartService $cartService,
        Connection $connection
    ) {
        $this->contextPersister = $contextPersister;
        $this->eventDispatcher = $eventDispatcher;
        $this->systemConfig = $systemConfig;
        $this->cartService = $cartService;
        $this->connection = $connection;
    }

    public function getDecorated(): AbstractLogoutRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @OA\Post(
     *      path="/account/logout",
     *      description="Logouts current loggedin customer",
     *      operationId="logoutCustomer",
     *      tags={"Store API", "Account"},
     *      @OA\Response(
     *          response="200",
     *          description=""
     *     )
     * )
     * @Route(path="/store-api/v{version}/account/logout", name="store-api.account.logout", methods={"POST"})
     */
    public function logout(SalesChannelContext $context, ?RequestDataBag $data = null)
    {
        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }

        $salesChannelId = $context->getSalesChannel()->getId();
        if ($this->systemConfig->get('core.loginRegistration.invalidateSessionOnLogOut', $salesChannelId)) {
            $this->cartService->deleteCart($context);
            $this->deleteContextToken($context->getToken());
        } elseif (Feature::isActive('FEATURE_NEXT_10058')) {
            $newToken = Random::getAlphanumericString(32);

            if ($data && (bool) $data->get('replace-token')) {
                $newToken = $this->contextPersister->replace($context->getToken(), $context);
            }

            $context->assign([
                'token' => $newToken,
            ]);
        } else {
            $this->contextPersister->save(
                $context->getToken(),
                [
                    'customerId' => null,
                    'billingAddressId' => null,
                    'shippingAddressId' => null,
                ]
            );
        }

        $event = new CustomerLogoutEvent($context, $context->getCustomer());
        $this->eventDispatcher->dispatch($event);

        return Feature::isActive('FEATURE_NEXT_10058') ? new ContextTokenResponse($context->getToken()) : new NoContentResponse();
    }

    /**
     * @deprecated tag:v6.3.0 use \Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister::delete
     */
    private function deleteContextToken(string $token): void
    {
        $this->connection->executeUpdate(
            'DELETE FROM sales_channel_api_context WHERE token = :token',
            [
                'token' => $token,
            ]
        );
    }
}
