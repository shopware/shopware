<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\SalesChannelRequestEnum;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('framework')]
class ResponseExceptionListener implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly bool $debug = false)
    {
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
        if ($event->getRequest()->attributes->get(SalesChannelRequestEnum::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST->value)) {
            return;
        }

        $exception = $event->getThrowable();

        $event->setResponse((new ErrorResponseFactory())->getResponseFromException($exception, $this->debug));
    }
}
