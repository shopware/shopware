<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test;

use Shopware\Core\Checkout\Payment\Controller\PaymentController;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviourTest;
use Symfony\Component\Routing\Router;

class RouteScopeTest extends KernelTestBehaviourTest
{
    public function testAllRoutesHaveRouteScopes(): void
    {
        /** @var Router $router */
        $router = $this->getKernel()->getContainer()->get('router');

        $routeCollection = $router->getRouteCollection();

        $errors = [];
        $errorMessage = 'No RouteScope defined for following Methods';

        foreach ($routeCollection as $route) {
            if (!$controllerMethod = $route->getDefault('_controller')) {
                continue;
            }

            $controllerMethod = explode('::', $controllerMethod);

            // The payment controller must work also without scopes due headless
            if ($controllerMethod[0] === PaymentController::class) {
                continue;
            }

            $routeMethodReflection = new \ReflectionMethod($controllerMethod[0], $controllerMethod[1]);
            $docBlock = $routeMethodReflection->getDocComment() ?: '';
            $pattern = "#@([a-zA-Z]+\s*)#";

            preg_match_all($pattern, $docBlock, $matches, \PREG_PATTERN_ORDER);

            if (!\in_array('RouteScope', $matches[1], true)) {
                $routeClassReflection = new \ReflectionClass($controllerMethod[0]);
                $docBlock = $routeClassReflection->getDocComment() ?: '';
                $pattern = "#@([a-zA-Z]+\s*)#";

                preg_match_all($pattern, $docBlock, $matches, \PREG_PATTERN_ORDER);

                if (\in_array('RouteScope', $matches[1], true)) {
                    continue;
                }

                $errors[] = $route->getDefault('_controller');
            }
        }

        $errorMessage .= "\n" . print_r($errors, true);

        static::assertCount(0, $errors, $errorMessage);
    }
}
