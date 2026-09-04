<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;

/**
 * Remaps the serializer's ExtraAttributesException to a content-system 400 for the admin content routes that opt
 * into strict request mapping (#[MapRequestPayload(serializationContext: [ALLOW_EXTRA_ATTRIBUTES => false])]).
 *
 * Without it, an unknown request field escapes as a 500: ExtraAttributesException is neither of the two types the
 * MapRequestPayload value resolver maps onto its validationFailedStatusCode, so it reaches the default error
 * renderer unmapped. ExtraAttributesException only arises where ALLOW_EXTRA_ATTRIBUTES is set, which in src/ is
 * only this module; the route-prefix gate keeps the remap scoped to the content-system admin routes regardless.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class UnknownRequestFieldExceptionListener implements EventSubscriberInterface
{
    private const ROUTE_PREFIX = 'api.action.content_system.';

    /**
     * @return array<string, array<int, array<int, string|int>>>
     */
    public static function getSubscribedEvents(): array
    {
        // Above ResponseExceptionListener (priority -1) so the throwable is remapped before the error renderer reads it.
        return [KernelEvents::EXCEPTION => [['onKernelException', 0]]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $route = $event->getRequest()->attributes->get('_route');

        if (!\is_string($route) || !str_starts_with($route, self::ROUTE_PREFIX)) {
            return;
        }

        $extraAttributes = $this->findExtraAttributes($event->getThrowable());

        if ($extraAttributes === null) {
            return;
        }

        $event->setThrowable(ContentSystemException::unknownRequestField($extraAttributes));
    }

    /**
     * The value resolver may wrap the serializer exception, so walk the cause chain rather than checking only the
     * top throwable.
     *
     * @return list<string>|null
     */
    private function findExtraAttributes(\Throwable $throwable): ?array
    {
        for ($current = $throwable; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof ExtraAttributesException) {
                return array_values(array_map(strval(...), $current->getExtraAttributes()));
            }
        }

        return null;
    }
}
