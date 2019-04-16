<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener\Authentication;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Routing\Exception\SalesChannelNotFoundException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class SalesChannelAuthenticationListener implements EventSubscriberInterface
{
    /**
     * @var string
     */
    private static $routePrefix = '/sales-channel-api/';

    /**
     * @var array[]
     */
    private static $unprotectedRoutes = [
        [
            'path' => '/sales-channel-api/v1/checkout/guest-order/',
            'methods' => ['GET'],
        ],
    ];

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
            KernelEvents::REQUEST => ['validateRequest', 32],
        ];
    }

    public function validateRequest(GetResponseEvent $event): void
    {
        $request = $event->getRequest();

        foreach (self::$unprotectedRoutes as $route) {
            if (stripos($request->getPathInfo(), $route['path']) === 0 && (!isset($route['methods']) || \in_array($request->getMethod(), $route['methods'], true))) {
                return;
            }
        }

        if (stripos($request->getPathInfo(), self::$routePrefix) !== 0) {
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
