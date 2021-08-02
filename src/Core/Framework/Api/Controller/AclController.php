<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Doctrine\Common\Annotations\AnnotationReader;
use Shopware\Core\Framework\Api\Acl\Event\AclGetAdditionalPrivilegesEvent;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Routing\Annotation\Acl;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Storefront\Framework\Cache\Annotation\HttpCache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class AclController extends AbstractController
{
    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionInstanceRegistry;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        DefinitionInstanceRegistry $definitionInstanceRegistry,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->definitionInstanceRegistry = $definitionInstanceRegistry;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @Since("6.3.3.0")
     * @HttpCache()
     * @Route("/api/_action/acl/privileges", name="api.acl.privileges.get", methods={"GET"}, defaults={"auth_required"=true})
     * @Acl({"api_acl_privileges_get"})
     */
    public function getPrivileges(): JsonResponse
    {
        $privileges = $this->getFromAnnotations();
        $privileges = array_merge($privileges, $this->getFromDefinitions());
        $privileges = array_unique($privileges);

        return new JsonResponse($privileges);
    }

    /**
     * @Since("6.3.3.0")
     * @Route("/api/_action/acl/additional_privileges", name="api.acl.privileges.additional.get", methods={"GET"}, defaults={"auth_required"=true})
     * @Acl({"api_acl_privileges_additional_get"})
     */
    public function getAdditionalPrivileges(Context $context): JsonResponse
    {
        $privileges = $this->getFromAnnotations();
        $definitionPrivileges = $this->getFromDefinitions();
        $privileges = array_diff(array_unique($privileges), $definitionPrivileges);

        $event = new AclGetAdditionalPrivilegesEvent($context, $privileges);
        $this->eventDispatcher->dispatch($event);

        $privileges = $event->getPrivileges();

        return new JsonResponse($privileges);
    }

    private function getFromAnnotations(): array
    {
        $privileges = [];
        $annotationReader = new AnnotationReader();
        $routes = $this->container->get('router')->getRouteCollection()->all();

        $seenServices = [];
        foreach ($routes as $param) {
            $defaults = $param->getDefaults();

            if (isset($defaults['_controller'])) {
                list($controllerService, $controllerMethod) = explode('::', $defaults['_controller']);
                if ($this->container->has($controllerService)) {
                    $reflectedMethod = new \ReflectionMethod(\get_class($this->container->get($controllerService)), $controllerMethod);
                    $annotation = $annotationReader->getMethodAnnotation($reflectedMethod, Acl::class);
                    $privileges = array_merge($privileges, $annotation ? $annotation->getValue() : []);

                    if (\in_array($controllerService, $seenServices, true)) {
                        continue;
                    }

                    $reflectedClass = new \ReflectionClass(\get_class($this->container->get($controllerService)));
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
}
