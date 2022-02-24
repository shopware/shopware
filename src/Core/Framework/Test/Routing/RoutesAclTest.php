<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Routing;

use PHPUnit\Framework\TestCase;
use Sensio\Bundle\FrameworkExtraBundle\EventListener\ControllerListener;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Route;

class RoutesAclTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * blacklist routes for explicit overwrite
     *
     * @var array
     */
    private $blocklist = [];

    public function testRoutesAcls(): void
    {
        /** @var ControllerListener $annotControllerListener */
        $annotControllerListener = $this->getContainer()->get('sensio_framework_extra.controller.listener');

        $routes = $this->getContainer()->get('router')->getRouteCollection();
        foreach ($routes as $route) {
            if (\in_array($route->getPath(), $this->blocklist, true)) {
                continue;
            }
            if (!\is_array($route->getOption('parentDefaults'))) {
                continue;
            }

            $parentCounts = \count($route->getOption('parentDefaults'));

            for ($x = 0; $x < $parentCounts; ++$x) {
                if (($defaultControllerEvent = $this->getDefaultEvent($route)) === null) {
                    return;
                }

                $annotControllerListener->onKernelController($defaultControllerEvent);

                if (($inheritedControllerEvent = $this->getEvent($route, $x)) === null) {
                    return;
                }

                $annotControllerListener->onKernelController($inheritedControllerEvent);

                static::assertEquals(
                    $inheritedControllerEvent->getRequest()->attributes->get('_acl'),
                    $defaultControllerEvent->getRequest()->attributes->get('_acl'),
                    'The route ' . $route->getPath() . ' is overwritten in ' . $route->getDefaults()['_controller']
                    . ' and is missing the ACL from parent' . print_r($route->getOption('parentDefaults'), true)
                );
            }
        }

        static::assertTrue(true, 'suppress the "no assertion" message');
    }

    private function getEvent(Route $route, int $parentNum): ?ControllerEvent
    {
        $defaultControllerEvent = new ControllerEvent(
            $this->getKernel(),
            $this->getCallable($route->getDefaults()['_controller']),
            new Request(),
            HttpKernelInterface::MASTER_REQUEST
        );

        $controllerCallable = $this->getCallable($route->getOption('parentDefaults')[$parentNum]['_controller']);

        $defaultControllerEvent->setController($controllerCallable);

        return $defaultControllerEvent;
    }

    private function getDefaultEvent(Route $route): ?ControllerEvent
    {
        return new ControllerEvent(
            $this->getKernel(),
            $this->getCallable($route->getDefaults()['_controller']),
            new Request(),
            HttpKernelInterface::MASTER_REQUEST
        );
    }

    private function getCallable(string $controllerMethod): array
    {
        $defaultControllerClass = explode(
            '::',
            $controllerMethod
        );

        $defaultController = $this->getContainer()->get($defaultControllerClass[0]);

        return [$defaultController, $defaultControllerClass[1]];
    }
}
