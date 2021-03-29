<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener;

use Shopware\Core\SalesChannelRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ResponseExceptionListener implements EventSubscriberInterface
{
    /**
     * @var bool
     */
    private $debug;

    public function __construct($debug = false)
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

    public function onKernelException(ExceptionEvent $event)
    {
        if ($event->getRequest()->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST)) {
            return $event;
        }

        $exception = $event->getThrowable();

        $event->setResponse((new ErrorResponseFactory())->getResponseFromException($exception, $this->debug));

        return $event;
    }
}
