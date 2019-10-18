<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Csrf;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Storefront\Framework\Csrf\CsrfRouteListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class CsrfRouteListenerTest extends TestCase
{
    use KernelTestBehaviour;

    public function testCsrfCheckShouldBeValid(): void
    {
        $listener = $this->createCsrfRouteListener();

        $request = new Request();
        $request->setMethod('POST');
        $routeScope = new RouteScope(['_routeScope' => ['storefront']]);
//        $routeScope->setScopes(['storefront']);
        $request->attributes->set('_routeScope', $routeScope);
        $event = new ControllerEvent(
            $this->getKernel(),
            function (): void {},
            $request,
            HttpKernelInterface::MASTER_REQUEST
        )
        ;
        try {
            $listener->csrfCheck($event);
        } catch (\Exception $exception) {
            dd($exception);
        }
    }

    private function createCsrfRouteListener(bool $csrfEnabled = true)
    {
        return new CsrfRouteListener(
            $this->getContainer()->get('security.csrf.token_manager'),
            $csrfEnabled,
            $this->getContainer()->get('session'),
            $this->getContainer()->get('translator')
        );
    }
}
