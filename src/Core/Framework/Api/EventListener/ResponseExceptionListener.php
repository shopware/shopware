<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener;

use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\SalesChannelRequest;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\EventListener\ExceptionListener;
use Symfony\Component\HttpKernel\KernelEvents;

class ResponseExceptionListener extends ExceptionListener
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => [
                ['logKernelException'],
                ['onKernelException'],
            ],
        ];
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        if ($event->getRequest()->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST)) {
            return $event;
        }

        $exception = $event->getException();

        $event->setResponse((new ErrorResponseFactory())->getResponseFromException($exception, $this->debug));

        return $event;
    }

    public function logException(\Exception $exception, $message): void
    {
        if ($this->logger === null) {
            return;
        }

        if (!$exception instanceof ShopwareHttpException || $exception->getStatusCode() >= 500) {
            parent::logException($exception, $message);
        } else {
            $this->logger->error($message, ['exception' => $exception]);
        }
    }
}
