<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener;

use Shopware\Core\Framework\Adapter\Cache\Http\CacheAttribute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Copies the fields of the compressed `_criteria` parameter into the query parameters,
 * so a cacheable Store API GET request is read the same way as a POST request with that body.
 *
 * @internal
 *
 * @phpstan-import-type CacheAttributeType from CacheAttribute
 */
#[Package('framework')]
class CompressedCriteriaRequestListener implements EventSubscriberInterface
{
    private const PARAMETER = '_criteria';

    public function __construct(private readonly CompressedCriteriaDecoder $decoder)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // runs after the access key check and before anything reads the request parameters
            KernelEvents::CONTROLLER => [
                'expandCompressedCriteria',
                KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_PRIORITY_AUTH_VALIDATE_POST,
            ],
        ];
    }

    public function expandCompressedCriteria(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->isMethod(Request::METHOD_GET) || !$request->query->has(self::PARAMETER)) {
            return;
        }

        if (!$this->isCacheableStoreApiRoute($request)) {
            return;
        }

        $payload = $this->decoder->decode((string) $request->query->get(self::PARAMETER));

        foreach ($payload as $field => $value) {
            $field = (string) $field;

            if ($field === self::PARAMETER) {
                continue;
            }

            // the compressed criteria wins over a plain query parameter of the same name
            $request->query->set($field, $value);
        }
    }

    /**
     * The compressed criteria exists to make read routes cacheable, every other route keeps reading its plain parameters only.
     */
    private function isCacheableStoreApiRoute(Request $request): bool
    {
        $scopes = (array) $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);
        if (!\in_array(StoreApiRouteScope::ID, $scopes, true)) {
            return false;
        }

        /** @var CacheAttributeType|null $cache */
        $cache = $request->attributes->get(PlatformRequest::ATTRIBUTE_HTTP_CACHE);

        return CacheAttribute::fromAttributeValue($cache) !== null;
    }
}
