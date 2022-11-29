<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener;

use Shopware\Core\SalesChannelRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
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
