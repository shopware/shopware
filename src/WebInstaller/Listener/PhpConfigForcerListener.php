<?php
declare(strict_types=1);

namespace Shopware\WebInstaller\Listener;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('framework')]
class PhpConfigForcerListener
{
    public function __construct(private readonly RouterInterface $router)
    {
    }

    #[AsEventListener(RequestEvent::class)]
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (\in_array($request->attributes->get('_route'), ['configure', 'index', null], true) || $request->getSession()->has('phpBinary')) {
            return;
        }

        $event->setResponse(
            new RedirectResponse($this->router->generate('configure'))
        );
    }
}
