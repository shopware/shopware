<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener\Authentication;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Routing\Exception\SalesChannelNotFoundException;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\Framework\Routing\RouteScopeCheckTrait;
use Shopware\Core\Framework\Routing\RouteScopeRegistry;
use Shopware\Core\Framework\Routing\SalesChannelApiRouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class SalesChannelAuthenticationListener implements EventSubscriberInterface
{
    use RouteScopeCheckTrait;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var RouteScopeRegistry
     */
    private $routeScopeRegistry;

    public function __construct(
        Connection $connection,
        RouteScopeRegistry $routeScopeRegistry
    ) {
        $this->connection = $connection;
        $this->routeScopeRegistry = $routeScopeRegistry;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['validateRequest', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_PRIORITY_AUTH_VALIDATE],
        ];
    }

    public function validateRequest(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->attributes->get('auth_required', true)) {
            return;
        }

        if (!$this->isRequestScoped($request, SalesChannelApiRouteScope::class)) {
            return;
        }

        if (!$request->headers->has(PlatformRequest::HEADER_ACCESS_KEY)) {
            throw new UnauthorizedHttpException('header', sprintf('Header "%s" is required.', PlatformRequest::HEADER_ACCESS_KEY));
        }

        $accessKey = $request->headers->get(PlatformRequest::HEADER_ACCESS_KEY);

        $origin = AccessKeyHelper::getOrigin($accessKey);
        if ($origin !== 'sales-channel') {
            throw new SalesChannelNotFoundException();
        }

        $salesChannelId = $this->getSalesChannelId($accessKey);

        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, $salesChannelId);
    }

    protected function getScopeRegistry(): RouteScopeRegistry
    {
        return $this->routeScopeRegistry;
    }

    private function getSalesChannelId(string $accessKey): string
    {
        $builder = $this->connection->createQueryBuilder();

        $salesChannelId = $builder->select(['sales_channel.id'])
            ->from('sales_channel')
            ->where('sales_channel.access_key = :accessKey')
            ->setParameter('accessKey', $accessKey)
            ->execute()
            ->fetchColumn();

        if (!$salesChannelId) {
            throw new SalesChannelNotFoundException();
        }

        return Uuid::fromBytesToHex($salesChannelId);
    }
}
