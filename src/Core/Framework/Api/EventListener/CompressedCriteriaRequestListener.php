<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener;

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
 * Copies the fields of the compressed `_criteria` parameter into the query parameters of a Store API GET request.
 * So `_criteria` is a compressed form of the query string, and a GET request reads the same fields as a POST request with that body,
 * except fields that a route reads from the body only.
 *
 * @internal
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
            // runs after the access key check and before the context, the argument resolvers and the controller read the request
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

        $scopes = (array) $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);
        if (!\in_array(StoreApiRouteScope::ID, $scopes, true)) {
            return;
        }

        $payload = $this->decoder->decode((string) $request->query->get(self::PARAMETER));

        foreach ($payload as $field => $value) {
            $field = (string) $field;

            if ($field === self::PARAMETER) {
                continue;
            }

            // the compressed criteria wins over a plain query parameter of the same name
            $request->query->set($field, self::toQueryValue($value));
        }
    }

    /**
     * Values become strings, as in a query string, so readers of the query parameters get the types they expect.
     * The criteria keep their JSON types, because the criteria builder decodes the parameter itself.
     */
    private static function toQueryValue(mixed $value): mixed
    {
        return match (true) {
            \is_array($value) => array_map(self::toQueryValue(...), $value),
            \is_bool($value) => $value ? '1' : '0',
            \is_scalar($value) => (string) $value,
            default => '',
        };
    }
}
