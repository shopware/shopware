<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Routing\Exception\SalesChannelMappingException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * @internal
 */
#[Package('storefront')]
readonly class DomainNotMappedListener
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!$event->getThrowable() instanceof SalesChannelMappingException) {
            return;
        }

        $debug = $this->container->getParameter('kernel.debug');
        $vars = [
            'debug' => $debug,
            'domain' => $event->getRequest()->getSchemeAndHttpHost(),
            'accessedUrl' => $event->getRequest()->getUri(),
            'registeredDomains' => $debug ? $this->container->get(Connection::class)->fetchFirstColumn('SELECT url FROM sales_channel_domain') : [],
            'relevantHeaders' => [
                'Host' => $event->getRequest()->headers->get('Host'),
                'X-Forwarded-Host' => $event->getRequest()->headers->get('X-Forwarded-Host'),
                'X-Forwarded-Proto' => $event->getRequest()->headers->get('X-Forwarded-Proto'),
                'X-Forwarded-For' => $event->getRequest()->headers->get('X-Forwarded-For'),
            ],
            'clientIp' => $event->getRequest()->getClientIp(),
            'trustedProxyRelevantHeaders' => array_keys(array_filter([
                'x-forwarded-host' => $event->getRequest()->headers->get('X-Forwarded-Host'),
                'x-forwarded-proto' => $event->getRequest()->headers->get('X-Forwarded-Proto'),
                'x-forwarded-for' => $event->getRequest()->headers->get('X-Forwarded-For'),
            ])),
        ];

        $event->setResponse(
            new Response($this->container->get('twig')->render('@Storefront/storefront/page/error/error-domain-mapping.html.twig', $vars))
        );
    }
}
