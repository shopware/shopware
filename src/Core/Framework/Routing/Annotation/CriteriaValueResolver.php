<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Annotation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

#[Package('core')]
class CriteriaValueResolver implements ValueResolverInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly RequestCriteriaBuilder $criteriaBuilder
    ) {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        if ($argument->getType() !== Criteria::class) {
            return;
        }

        /** @var string|null $entity */
        $entity = $request->attributes->get(PlatformRequest::ATTRIBUTE_ENTITY);

        if (!$entity) {
            $route = $request->attributes->get('_route');

            throw new \RuntimeException('Missing _entity route default for route: ' . $route);
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        if (!$context instanceof Context) {
            $route = $request->attributes->get('_route');

            throw new \RuntimeException('Missing context for route ' . $route);
        }

        yield $this->criteriaBuilder->handleRequest(
            $request,
            new Criteria(),
            $this->registry->getByEntityName($entity),
            $context
        );
    }
}
