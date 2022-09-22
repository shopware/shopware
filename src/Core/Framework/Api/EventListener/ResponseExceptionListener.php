<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener;

use Shopware\Core\SalesChannelRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - EventSubscribers will become internal in v6.5.0
 */
class ResponseExceptionListener implements EventSubscriberInterface
{
    private bool $debug;

    /**
     * @internal
     */
    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => [
                ['onKernelException', -1],
            ],
        ];
    }

    /**
     * @deprecated tag:v6.5.0 - reason:return-type-change - The return type will be changed to void in v6.5.0
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        if (
            $event->getRequest()->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST)
            && !$event->getRequest()->attributes->has(SalesChannelRequest::ATTRIBUTE_STORE_API_PROXY)
        ) {
            return;
        }

        $exception = $event->getThrowable();

        $event->setResponse((new ErrorResponseFactory())->getResponseFromException($exception, $this->debug));
    }
}
