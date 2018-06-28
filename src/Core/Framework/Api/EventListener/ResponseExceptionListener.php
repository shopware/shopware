<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Response\ResponseFactory;
use Shopware\Core\Framework\ShopwareException;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\EventListener\ExceptionListener;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ResponseExceptionListener extends ExceptionListener
{
    /**
     * @var ResponseFactory
     */
    private $responseFactory;

    public function __construct(LoggerInterface $logger, ResponseFactory $apiFactory)
    {
        parent::__construct(null, $logger);
        $this->logger = $logger;
        $this->responseFactory = $apiFactory;
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        /** @var HttpException|ShopwareException $exception */
        $exception = $event->getException();

        $this->logException($exception, sprintf('Uncaught PHP Exception %s: "%s" at %s line %s', \get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine()));

        $statusCode = 500;
        if ($exception instanceof HttpException) {
            $statusCode = $exception->getStatusCode();
        }

        $response = $this->responseFactory->createErrorResponse(
            $event->getRequest(),
            $exception,
            $statusCode
        );

        $event->setResponse($response);

        return $event;
    }
}
