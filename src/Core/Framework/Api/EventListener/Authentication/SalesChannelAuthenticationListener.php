<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener\Authentication;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Exception\SalesChannelNotFoundException;
use Shopware\Core\Framework\Routing\SalesChannelApiRouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class SalesChannelAuthenticationListener implements EventSubscriberInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['validateRequest', Defaults::KERNEL_CONTROLLER_EVENT_PRIORITY_AUTH_VALIDATE],
        ];
    }

    public function validateRequest(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->attributes->get('auth_required', true)) {
            return;
        }

        /** @var RouteScope|null $routeScope */
        $routeScope = $request->attributes->get('_routeScope');
        if ($routeScope === null || !$routeScope->hasScope(SalesChannelApiRouteScope::ID)) {
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
