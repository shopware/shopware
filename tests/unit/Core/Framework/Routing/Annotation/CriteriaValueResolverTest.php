<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing\Annotation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Annotation\CriteriaValueResolver;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CriteriaValueResolver::class)]
class CriteriaValueResolverTest extends TestCase
{
    private DefinitionInstanceRegistry&MockObject $registry;

    private RequestCriteriaBuilder&MockObject $criteriaBuilder;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(DefinitionInstanceRegistry::class);
        $this->criteriaBuilder = $this->createMock(RequestCriteriaBuilder::class);
    }

    public function testStoresResolvedCriteriaOnRequest(): void
    {
        $request = new Request();
        $context = Context::createDefaultContext();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ENTITY, 'product');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);

        $definition = static::createStub(EntityDefinition::class);
        $criteria = new Criteria();
        $criteria->setIncludes(['product' => ['id']]);

        $this->registry->expects($this->once())
            ->method('getByEntityName')
            ->with('product')
            ->willReturn($definition);
        $this->criteriaBuilder->expects($this->once())
            ->method('handleRequest')
            ->willReturnCallback(static function (Request $resolvedRequest, Criteria $resolvedCriteria, EntityDefinition $resolvedDefinition, Context $resolvedContext) use ($request, $definition, $criteria, $context): Criteria {
                static::assertSame($request, $resolvedRequest);
                static::assertSame($definition, $resolvedDefinition);
                static::assertSame($context, $resolvedContext);
                static::assertEmpty($resolvedCriteria->getIncludes());

                return $criteria;
            });

        $resolver = new CriteriaValueResolver($this->registry, $this->criteriaBuilder);
        $resolvedCriteria = iterator_to_array($resolver->resolve(
            $request,
            new ArgumentMetadata('criteria', Criteria::class, false, false, null)
        ));

        static::assertSame([$criteria], $resolvedCriteria);
        static::assertSame($criteria, $request->attributes->get(PlatformRequest::ATTRIBUTE_CRITERIA));
    }
}
