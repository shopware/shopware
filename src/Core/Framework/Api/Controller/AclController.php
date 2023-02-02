<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Doctrine\Common\Annotations\AnnotationReader;
use Shopware\Core\Framework\Api\Acl\Event\AclGetAdditionalPrivilegesEvent;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Annotation\Acl;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use function array_merge;

/**
 * @Route(defaults={"_routeScope"={"api"}})
 */
class AclController extends AbstractController
{
    private DefinitionInstanceRegistry $definitionInstanceRegistry;

    private EventDispatcherInterface $eventDispatcher;

    private RouterInterface $router;

    /**
     * @internal
     */
    public function __construct(
        DefinitionInstanceRegistry $definitionInstanceRegistry,
        EventDispatcherInterface $eventDispatcher,
        RouterInterface $router
    ) {
        $this->definitionInstanceRegistry = $definitionInstanceRegistry;
        $this->eventDispatcher = $eventDispatcher;
        $this->router = $router;
    }

    /**
     * @Since("6.3.3.0")
     * @Route("/api/_action/acl/privileges", name="api.acl.privileges.get", methods={"GET"}, defaults={"auth_required"=true, "_acl"={"api_acl_privileges_get"}})
     */
    public function getPrivileges(): JsonResponse
    {
        if (Feature::isActive('v6.5.0.0')) {
            $privileges = $this->getFromRoutes();
        } else {
            $privileges = array_merge($this->getFromAnnotations(), $this->getFromRoutes());
        }

        $privileges = array_unique(array_merge($privileges, $this->getFromDefinitions()));

        return new JsonResponse($privileges);
    }

    /**
     * @Since("6.3.3.0")
     * @Route("/api/_action/acl/additional_privileges", name="api.acl.privileges.additional.get", methods={"GET"}, defaults={"auth_required"=true, "_acl"={"api_acl_privileges_additional_get"}})
     */
    public function getAdditionalPrivileges(Context $context): JsonResponse
    {
        if (Feature::isActive('v6.5.0.0')) {
            $privileges = $this->getFromRoutes();
        } else {
            $privileges = array_merge($this->getFromAnnotations(), $this->getFromRoutes());
        }

        $definitionPrivileges = $this->getFromDefinitions();
        $privileges = array_diff(array_unique($privileges), $definitionPrivileges);

        $event = new AclGetAdditionalPrivilegesEvent($context, $privileges);
        $this->eventDispatcher->dispatch($event);

        $privileges = $event->getPrivileges();

        return new JsonResponse($privileges);
    }

    /**
     * @deprecated tag:v6.5.0 - Use getFromRoutes instead
     */
    private function getFromAnnotations(): array
    {
        $privileges = [];
        $annotationReader = new AnnotationReader();
        $routes = $this->router->getRouteCollection()->all();

        $seenServices = [];
        foreach ($routes as $param) {
            $defaults = $param->getDefaults();

            if (isset($defaults['_controller'])) {
                $controllerInfo = explode('::', $defaults['_controller']);

                $controllerService = $controllerInfo[0];
                $controllerMethod = $controllerInfo[1] ?? '__invoke';

                if ($this->container->has($controllerService)) {
                    $className = \get_class($this->container->get($controllerService));
                    \assert(\is_string($className));
                    $reflectedMethod = new \ReflectionMethod($className, $controllerMethod);
                    $annotation = $annotationReader->getMethodAnnotation($reflectedMethod, Acl::class);
                    $privileges = array_merge($privileges, $annotation ? $annotation->getValue() : []);

                    if (\in_array($controllerService, $seenServices, true)) {
                        continue;
                    }

                    $reflectedClass = new \ReflectionClass($className);
                    $annotation = $annotationReader->getClassAnnotation($reflectedClass, Acl::class);
                    $privileges = array_merge($privileges, $annotation ? $annotation->getValue() : []);
                    $seenServices[] = $controllerService;
                }
            }
        }

        return $privileges;
    }

    private function getFromDefinitions(): array
    {
        $privileges = [];
        $definitions = $this->definitionInstanceRegistry->getDefinitions();

        foreach ($definitions as $key => $_definition) {
            $privileges[] = $key . ':' . AclRoleDefinition::PRIVILEGE_CREATE;
            $privileges[] = $key . ':' . AclRoleDefinition::PRIVILEGE_DELETE;
            $privileges[] = $key . ':' . AclRoleDefinition::PRIVILEGE_READ;
            $privileges[] = $key . ':' . AclRoleDefinition::PRIVILEGE_UPDATE;
        }

        return $privileges;
    }

    private function getFromRoutes(): array
    {
        $permissions = [];

        foreach ($this->router->getRouteCollection()->all() as $route) {
            if ($acl = $route->getDefault(PlatformRequest::ATTRIBUTE_ACL)) {
                $permissions[] = $acl;
            }
        }

        return array_merge(...$permissions);
    }
}
