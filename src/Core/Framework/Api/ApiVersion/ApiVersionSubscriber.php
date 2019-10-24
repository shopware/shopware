<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiVersion;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiVersionSubscriber implements EventSubscriberInterface
{
    /**
     * @var int[]
     */
    private $supportedApiVersions;

    public function __construct(array $supportedApiVersions)
    {
        $this->supportedApiVersions = $supportedApiVersions;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => 'checkIfVersionIsSupported',
        ];
    }

    public function checkIfVersionIsSupported(RequestEvent $event): void
    {
        $path = $event->getRequest()->getPathInfo();
        $matches = [];
        // https://regex101.com/r/BHG1ab/1
        if (!preg_match('/^\/(api|sales-channel-api)\/v(?P<version>\d)\/.*$/', $path, $matches)) {
            return;
        }

        $requestedVersion = (int) $matches['version'];

        if (in_array($requestedVersion, $this->supportedApiVersions, true)) {
            return;
        }

        throw new NotFoundHttpException(
            sprintf(
                'Requested api version v%d not available, available versions are v%s.',
                $requestedVersion,
                implode(', v', $this->supportedApiVersions)
            )
        );
    }
}
