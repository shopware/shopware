<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Routing;

use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
class RoutesHaveSinceAnnotationTest extends TestCase
{
    use KernelTestBehaviour;

    public function testAllRoutesHaveSinceAnnotation(): void
    {
        $routes = $this->getContainer()->get(RouterInterface::class)->getRouteCollection();
        $platformDir = $this->getContainer()->getParameter('kernel.project_dir') . '/platform/';

        $missingSinceAnnotationOnRoutes = [];
        $annotationReader = new AnnotationReader();

        foreach ($routes as $routeName => $route) {
            try {
                /** @var class-string<object> $controllerClass */
                $controllerClass = strtok($route->getDefault('_controller'), ':');
                $refClass = new \ReflectionClass($controllerClass);
            } catch (\Throwable) {
                // Symfony uses for their own controllers alias. We cannot find them easily
                continue;
            }

            // File is not in Platform Directory
            if (!str_starts_with((string) $refClass->getFileName(), (string) $platformDir)) {
                continue;
            }

            foreach ($refClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $routeAnnotation = $annotationReader->getMethodAnnotation($method, Route::class);

                if ($routeAnnotation) {
                    $sinceAnnotation = $annotationReader->getMethodAnnotation($method, Since::class);

                    if ($sinceAnnotation === null) {
                        $missingSinceAnnotationOnRoutes[] = $routeName;
                    }
                }
            }
        }

        static::assertCount(0, $missingSinceAnnotationOnRoutes, sprintf('Following routes does not have a since annotation: %s', implode(', ', $missingSinceAnnotationOnRoutes)));
    }
}
