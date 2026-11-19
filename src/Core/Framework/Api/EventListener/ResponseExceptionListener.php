<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener;

use Doctrine\DBAL\Exception\ConnectionException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\SalesChannelRequest;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
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
        if ($event->getRequest()->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST)) {
            return;
        }

        $exception = $event->getThrowable();

        if (!$this->debug && $exception instanceof ConnectionException) {
            if ($event->getRequest()->getPreferredFormat() === 'html') {
                $errorPage = (new HtmlErrorRenderer(false))->render($exception);
                $event->setResponse(new Response($errorPage->getAsString(), $errorPage->getStatusCode(), $errorPage->getHeaders()));

                return;
            }

            $exception = new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR, Response::$statusTexts[Response::HTTP_INTERNAL_SERVER_ERROR], $exception);
        }

        $response = (new ErrorResponseFactory())->getResponseFromException($exception, $this->debug);

        // routes wrap the rate limit exception in their own, the throttled exceptions share the status and the seconds parameter
        if ($exception instanceof ShopwareHttpException
            && $exception->getStatusCode() === Response::HTTP_TOO_MANY_REQUESTS
            && \is_int($seconds = $exception->getParameter('seconds'))) {
            $response->headers->set('Retry-After', (string) max(0, $seconds));
        }

        $event->setResponse($response);
    }
}
