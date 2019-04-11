<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\VersionTransformation;

use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiVersionTransformationControllerListener implements EventSubscriberInterface
{
    /**
     * @var VersionTransformationRegistry
     */
    private $versionTransformationRegistry;

    public function __construct(VersionTransformationRegistry $versionTransformationRegistry)
    {
        $this->versionTransformationRegistry = $versionTransformationRegistry;
    }

    public function onKernelController(FilterControllerEvent $event)
    {
        $controller = $event->getController();

        /*
         * if $controller is a closure, do Nothing
         */
        if (!is_array($controller)) {
            return;
        }

        $version = (int) $event->getRequest()->headers->get(PlatformRequest::HEADER_API_VERSION);

        if ($version === null) {
            return;
        }

        /** @var ApiVersionTransformation $transformation */
        foreach ($this->versionTransformationRegistry->getRequestTransformationsForVersionAndRoute($version, $event->getRequest()->get('_route')) as $transformation) {
            $transformation->transformRequest($event->getRequest());
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }
}
