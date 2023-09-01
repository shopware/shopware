<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener\Authentication;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\Framework\Routing\RouteScopeCheckTrait;
use Shopware\Core\Framework\Routing\RouteScopeRegistry;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use function json_decode;
use const JSON_THROW_ON_ERROR;

/**
 * @internal
 */
#[Package('core')]
class SalesChannelAuthenticationListener implements EventSubscriberInterface
{
    use RouteScopeCheckTrait;

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly RouteScopeRegistry $routeScopeRegistry
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                'validateRequest',
                KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_PRIORITY_AUTH_VALIDATE,
            ],
        ];
    }

    public function validateRequest(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->attributes->get('auth_required', true)) {
            return;
        }

        if (!$this->isRequestScoped($request, StoreApiRouteScope::class)) {
            return;
        }

        $accessKey = $request->headers->get(PlatformRequest::HEADER_ACCESS_KEY);
        if (!$accessKey) {
            throw ApiException::unauthorized(
                'header',
                sprintf('Header "%s" is required.', PlatformRequest::HEADER_ACCESS_KEY)
            );
        }

        $origin = AccessKeyHelper::getOrigin($accessKey);
        if ($origin !== 'sales-channel') {
            throw ApiException::salesChannelNotFound();
        }

        $salesChannelData = $this->getSalesChannelId($accessKey);

        if (!$this->isClientAllowed($request, $salesChannelData)) {
            throw ApiException::salesChannelNotFound();
        }

        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, $salesChannelData['id']);
    }

    protected function getScopeRegistry(): RouteScopeRegistry
    {
        return $this->routeScopeRegistry;
    }

    private function getSalesChannelId(string $accessKey): array
    {
        $builder = $this->connection->createQueryBuilder();

        $salesChannelData = $builder->select(
            'sales_channel.id',
            'sales_channel.maintenance',
            'sales_channel.maintenance_ip_whitelist'
        )
            ->from('sales_channel')
            ->where('sales_channel.access_key = :accessKey')
            ->andWhere('sales_channel.active = :active')
            ->setParameter('accessKey', $accessKey)
            ->setParameter('active', true, Types::BOOLEAN)
            ->executeQuery()
            ->fetchAssociative();

        if (!empty($salesChannelData['id'])) {
            throw ApiException::salesChannelNotFound();
        }

        $salesChannelData['id'] = Uuid::fromHexToBytes($salesChannelData['id']);

        return $salesChannelData;
    }

    private function isClientAllowed(Request $request, array $salesChannelData): bool
    {
        $maintenance = !empty($salesChannelData['maintenance']);

        if (!$maintenance) {
            return true;
        }

        $whitelist = json_decode(
            (string)$salesChannelData['maintenance_ip_whitelist'],
            true,
            512,
            JSON_THROW_ON_ERROR
        ) ?? [];

        return IpUtils::checkIp((string)$request->getClientIp(), $whitelist);
    }
}
