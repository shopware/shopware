<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @Route(defaults={"_routeScope"={"store-api"}})
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
     * @internal
     */
    public function __construct(
        SalesChannelContextPersister $contextPersister,
        EventDispatcherInterface $eventDispatcher,
        SystemConfigService $systemConfig,
        CartService $cartService
    ) {
        $this->contextPersister = $contextPersister;
        $this->eventDispatcher = $eventDispatcher;
        $this->systemConfig = $systemConfig;
        $this->cartService = $cartService;
    }

    public function getDecorated(): AbstractLogoutRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     * @Route(path="/store-api/account/logout", name="store-api.account.logout", methods={"POST"}, defaults={"_loginRequired"=true, "_loginRequiredAllowGuest"=true})
     */
    public function logout(SalesChannelContext $context, RequestDataBag $data): ContextTokenResponse
    {
        /** @var CustomerEntity $customer */
        $customer = $context->getCustomer();
        if ($this->shouldDelete($context)) {
            $this->cartService->deleteCart($context);
            $this->contextPersister->delete($context->getToken(), $context->getSalesChannelId());

            $event = new CustomerLogoutEvent($context, $customer);
            $this->eventDispatcher->dispatch($event);

            return new ContextTokenResponse($context->getToken());
        }

        $newToken = Random::getAlphanumericString(32);
        if ((bool) $data->get('replace-token')) {
            $newToken = $this->contextPersister->replace($context->getToken(), $context);
        }

        $context->assign([
            'token' => $newToken,
        ]);

        $event = new CustomerLogoutEvent($context, $customer);
        $this->eventDispatcher->dispatch($event);

        return new ContextTokenResponse($context->getToken());
    }

    private function shouldDelete(SalesChannelContext $context): bool
    {
        $config = $this->systemConfig->get('core.loginRegistration.invalidateSessionOnLogOut', $context->getSalesChannelId());

        if ($config) {
            return true;
        }

        if ($context->getCustomer() === null) {
            return true;
        }

        return $context->getCustomer()->getGuest();
    }
}
