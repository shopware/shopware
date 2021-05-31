<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Routing;

use PHPUnit\Framework\TestCase;
use Sensio\Bundle\FrameworkExtraBundle\EventListener\ControllerListener;
use Shopware\Core\Framework\Api\Route\ApiRouteLoader;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Routing\Annotation\Acl;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Storefront\Framework\Routing\Router;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Loader\AnnotationClassLoader;
use Symfony\Component\Routing\Loader\AnnotationDirectoryLoader;
use Symfony\Component\Routing\Route;

class RoutesAclTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * blacklist routes for explizit overwrite
     *
     * @var array
     */
    private $blacklist = [
    ];

    private \Symfony\Component\Routing\RouteCollection $routes;

    private Router $router;

    public function setUp(): void
    {
        /** @var Router $router */
        $router = $this->getContainer()->get('router');
        $this->router = $router;
        /** @var AnnotationDirectoryLoader $annotDirLoader */
        $annotDirLoader = $this->getContainer()->get('routing.loader.annotation.directory');
        /** @var FileLocator $fileLocator */
        $fileLocator = $this->getContainer()->get('file_locator');
        /** @var AnnotationClassLoader $annotClassLoader */
        $annotClassLoader = $this->getContainer()->get('routing.loader.annotation');
        $testRouteLoader = new TestRouteLoader(
            $annotDirLoader,
            $fileLocator,
            $annotClassLoader
        );

        // Don't ask me why the next 11 lines are nessecary, but they are
        $this->getContainer()->set(
            'routing.loader.api.test',
            new ApiRouteLoader($this->getContainer()->get(DefinitionInstanceRegistry::class))
        );
        new LoaderResolver([
            $this->getContainer()->get('routing.loader.yml'),
            $this->getContainer()->get('routing.loader.php'),
            $this->getContainer()->get('routing.loader.xml'),
            $this->getContainer()->get('routing.loader.api.test'),
            $testRouteLoader,
        ]);

        $this->routes = $this->getKernel()->loadRoutes($testRouteLoader);
    }

    public function testRoutesAcls(): void
    {
        /** @var ControllerListener $annotControllerListener */
        $annotControllerListener = $this->getContainer()->get('sensio_framework_extra.controller.listener');

        foreach ($this->routes as $route) {
            if (\in_array($route->getPath(), $this->blacklist, true)) {
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

    private function enrichAcl(ControllerEvent $event, ControllerEvent $defaultControllerEvent): void
    {
        $orgRequestAttr = $event->getRequest()->attributes;
        $defaultRequestAttr = $defaultControllerEvent->getRequest()->attributes;
        /** @var Acl $orgAcl */
        $orgAcl = $orgRequestAttr->get('_acl');

        /** @var Acl $defaultAcl */
        $defaultAcl = $defaultRequestAttr->get('_acl');
        if (!($orgAcl instanceof Acl) && $defaultAcl instanceof Acl) {
            $orgRequestAttr->set('_acl', $defaultAcl);
        } elseif ($orgAcl instanceof Acl && $defaultAcl instanceof Acl) {
            $orgAcl->setValue(array_merge($orgAcl->getValue(), $defaultAcl->getValue()));
        }
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
