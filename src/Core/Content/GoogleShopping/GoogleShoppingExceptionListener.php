<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping;

use Shopware\Core\Content\GoogleShopping\Exception\GoogleShoppingServiceException;
use Shopware\Core\Framework\Api\EventListener\ErrorResponseFactory;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class GoogleShoppingExceptionListener
{
    /**
     * @var bool
     */
    private $debug;

    public function __construct($debug = false)
    {
        $this->debug = $debug;
    }

    public function onKernelException(ExceptionEvent $event)
    {
        // You get the exception object from the received event
        $exception = $event->getThrowable();

        if ($exception instanceof \Google_Service_Exception) {
            $serviceException = new GoogleShoppingServiceException($exception);
            $event->setResponse((new ErrorResponseFactory())->getResponseFromException($serviceException, $this->debug));
        }

        return $event;
    }
}
