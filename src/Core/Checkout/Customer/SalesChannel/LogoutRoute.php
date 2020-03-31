<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Response;
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

    public function __construct(SalesChannelContextPersister $contextPersister, EventDispatcherInterface $eventDispatcher)
    {
        $this->contextPersister = $contextPersister;
        $this->eventDispatcher = $eventDispatcher;
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
    public function logout(SalesChannelContext $context): NoContentResponse
    {
        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }

        $this->contextPersister->save(
            $context->getToken(),
            [
                'customerId' => null,
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ]
        );

        $event = new CustomerLogoutEvent($context, $context->getCustomer());
        $this->eventDispatcher->dispatch($event);

        return new NoContentResponse();
    }
}
