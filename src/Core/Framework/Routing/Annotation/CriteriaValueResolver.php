<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Annotation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class CriteriaValueResolver implements ArgumentValueResolverInterface
{
    /**
     * @var DefinitionInstanceRegistry
     */
    private $registry;

    /**
     * @var RequestCriteriaBuilder
     */
    private $criteriaBuilder;

    public function __construct(DefinitionInstanceRegistry $registry, RequestCriteriaBuilder $criteriaBuilder)
    {
        $this->registry = $registry;
        $this->criteriaBuilder = $criteriaBuilder;
    }

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === Criteria::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $annotation = $request->attributes->get('_entity');

        if (!$annotation instanceof Entity) {
            $route = $request->attributes->get('_route');

            throw new \RuntimeException('Missing @Entity annotation for route: ' . $route);
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        if (!$context instanceof Context) {
            $route = $request->attributes->get('_route');

            throw new \RuntimeException('Missing context for route ' . $route);
        }

        $criteria = $this->criteriaBuilder->handleRequest(
            $request,
            new Criteria(),
            $this->registry->getByEntityName($annotation->getValue()),
            $context
        );

        yield $criteria;
    }
}
