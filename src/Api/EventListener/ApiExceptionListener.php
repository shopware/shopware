<?php declare(strict_types=1);

namespace Shopware\Api\EventListener;

use Psr\Log\LoggerInterface;
use Shopware\Api\ResponseEnvelope;
use Shopware\Framework\Routing\Router;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\EventListener\ExceptionListener;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiExceptionListener extends ExceptionListener
{
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct(null, $logger);
        $this->logger = $logger;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::EXCEPTION => [
                ['onKernelException', -100],
            ],
        ];
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        if ($event->getException() instanceof HttpException === false
            || $event->getRequest()->attributes->get(Router::IS_API_REQUEST_ATTRIBUTE) === false) {
            return $event;
        }

        /** @var HttpException $exception */
        $exception = $event->getException();

        $this->logException($exception, sprintf('Uncaught PHP Exception %s: "%s" at %s line %s', get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine()));

        $envelope = new ResponseEnvelope();
        $envelope->setParameters($event->getRequest()->request->all());
        $envelope->setException($exception);

        $response = JsonResponse::create($envelope, $exception->getStatusCode());
        $event->setResponse($response);

        return $event;
    }
}
