<?php declare(strict_types=1);

namespace Shopware\Administration\Framework\Routing\NotFound;

use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\Controller\TemplateController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('administration')]
class AdministrationNotFoundSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly string $adminPath,
        private readonly TemplateController $templateController
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onError',
        ];
    }

    public function onError(ExceptionEvent $event): void
    {
        $path = $event->getRequest()->getPathInfo();
        $isAdminPath = str_starts_with($path, '/' . $this->adminPath);

        $is404StatusCode = $event->getThrowable() instanceof HttpException && $event->getThrowable()->getStatusCode() === Response::HTTP_NOT_FOUND;

        if (!$is404StatusCode || !$isAdminPath) {
            return;
        }

        $response = $this->templateController->templateAction('@Administration/administration/error-404.html.twig');

        $event->setResponse($response);
    }
}
