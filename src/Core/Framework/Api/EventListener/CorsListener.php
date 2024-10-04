<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('core')]
class CorsListener implements EventSubscriberInterface
{

    public function __construct(private Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 9999],
            KernelEvents::RESPONSE => ['onKernelResponse', 9999],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        if ($event->getRequest()->getRealMethod() === 'OPTIONS') {
            $event->setResponse(new Response(null, Response::HTTP_NO_CONTENT));
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $origin = $request->headers->get('Origin');
        if ($origin === null) {
            return;
        }

        $origin = rtrim($origin, '/');
        if (!str_starts_with($origin, 'http')) {
            return;
        }

        $statement = $this->connection->prepare(
            'SELECT 1 FROM `sales_channel_domain` LEFT JOIN `sales_channel` ON `sales_channel_domain`.`sales_channel_id` = `sales_channel`.`id` WHERE `sales_channel`.`active` = 1 AND `sales_channel`.`type_id` = unhex(?) AND `sales_channel_domain`.`url` LIKE ? LIMIT 1'
        );
        if (!$statement->executeQuery([Defaults::SALES_CHANNEL_TYPE_STOREFRONT, $origin . '%',])->rowCount()) {
            return;
        }

        /**
         * Origin
         */
        $response = $event->getResponse();
        $response->headers->set('Access-Control-Allow-Origin', $request->headers->get('origin'));

        if ($request->getRealMethod() !== 'OPTIONS') {
            return;
        }
        $response->setPrivate();

        /**
         * depends on the shopware version. only valid frontend api headers needed
         * @link vendor/shopware/core/PlatformRequest.php
         * @link \Shopware\Core\Framework\Api\EventListener\CorsListener::onKernelResponse
         */
        $corsHeaders = [
            'Content-Type',
            'Authorization',
            PlatformRequest::HEADER_CONTEXT_TOKEN,
            PlatformRequest::HEADER_ACCESS_KEY,
            PlatformRequest::HEADER_LANGUAGE_ID,
            PlatformRequest::HEADER_CURRENCY_ID,
            PlatformRequest::HEADER_INHERITANCE,
            PlatformRequest::HEADER_VERSION_ID,
            PlatformRequest::HEADER_INCLUDE_SEO_URLS,
        ];
        if ($headers = $request->headers->get('Access-Control-Request-Headers')) {
            $corsHeaders = array_merge($corsHeaders, explode(',', $headers));
            $corsHeaders = array_map(fn(string $header) => trim(strtolower($header)), $corsHeaders);
            $corsHeaders = array_unique($corsHeaders);
        }
        $corsHeaders = implode(', ', $corsHeaders);
        $response->headers->set('Access-Control-Allow-Headers', $corsHeaders);
        $response->headers->set('Access-Control-Expose-Headers', $corsHeaders);

        /**
         * Methods
         */
        $corsMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        if ($methods = $request->headers->get('Access-Control-Request-Method')) {
            $corsMethods = array_merge($corsMethods, explode(',', $methods));
            $corsMethods = array_map(fn(string $header) => trim(strtoupper($methods)), $corsMethods);
            $corsMethods = array_unique($corsMethods);
        }
        $corsMethods = implode(', ', $corsMethods);
        $response->headers->set('Access-Control-Allow-Methods', $corsMethods);

        /**
         * Cache Control
         */
        $response->headers->set('Access-Control-Max-Age', '3600');

        $event->setResponse($response);
    }
}
