<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutBindingGate;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutResolvabilityValidator;
use Shopware\Core\Framework\ContentSystem\Validation\ViolationConstraintMapper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(LayoutBindingGate::class)]
class LayoutBindingGateTest extends TestCase
{
    #[TestDox('returns no violations for a non-string layout id without touching the store')]
    public function testReturnsNoViolationsForNonStringLayoutId(): void
    {
        $gate = new LayoutBindingGate(
            static::createStub(DefinitionInstanceRegistry::class),
            static::createStub(LayoutResolvabilityValidator::class),
            new ViolationConstraintMapper(),
        );

        static::assertCount(0, $gate->bindingViolations(null, [], Context::createDefaultContext()));
    }

    #[TestDox('returns no violations for an empty layout id without touching the store')]
    public function testReturnsNoViolationsForEmptyLayoutId(): void
    {
        $gate = new LayoutBindingGate(
            static::createStub(DefinitionInstanceRegistry::class),
            static::createStub(LayoutResolvabilityValidator::class),
            new ViolationConstraintMapper(),
        );

        static::assertCount(0, $gate->bindingViolations('', [], Context::createDefaultContext()));
    }

    #[TestDox('returns no violations when the bound layout is not (yet) loadable')]
    public function testReturnsNoViolationsWhenLayoutNotFound(): void
    {
        $gate = new LayoutBindingGate(
            $this->registryReturning($this->searchResult(null)),
            static::createStub(LayoutResolvabilityValidator::class),
            new ViolationConstraintMapper(),
        );

        static::assertCount(0, $gate->bindingViolations(Uuid::randomHex(), [], Context::createDefaultContext()));
    }

    #[TestDox('maps the binding-scope violations of an unresolvable loaded layout')]
    public function testMapsBindingViolationsForUnresolvableLayout(): void
    {
        $layout = static::createStub(ContentLayoutEntity::class);
        $layout->method('getLayout')->willReturn([new ContentElement('el-1', 'Sw:Test:RequiresEntity')]);

        $resolvability = static::createStub(LayoutResolvabilityValidator::class);
        $resolvability->method('resolvability')->willReturn(new DiagnosticsReport([
            new Violation(ViolationCode::UnresolvedRequired, 'el-1', 'target', 'Required property "target" is not deterministically resolvable.'),
        ]));

        $gate = new LayoutBindingGate(
            $this->registryReturning($this->searchResult($layout)),
            $resolvability,
            new ViolationConstraintMapper(),
        );

        $violations = $gate->bindingViolations(Uuid::randomHex(), [], Context::createDefaultContext());

        static::assertCount(1, $violations);
        static::assertSame(ViolationCode::UnresolvedRequired->value, $violations->get(0)->getCode());
    }

    #[TestDox('converts a binary uuid to hex before loading the bound layout')]
    public function testConvertsBinaryUuidToHexBeforeLoading(): void
    {
        $hex = Uuid::randomHex();

        $layout = static::createStub(ContentLayoutEntity::class);
        $layout->method('getLayout')->willReturn([new ContentElement('el-1', 'Sw:Test:RequiresEntity')]);

        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturnCallback(
            fn (Criteria $criteria): EntitySearchResult => $criteria->getIds() === [$hex] ? $this->searchResult($layout) : $this->searchResult(null)
        );
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturn($repository);

        $resolvability = static::createStub(LayoutResolvabilityValidator::class);
        $resolvability->method('resolvability')->willReturn(new DiagnosticsReport([
            new Violation(ViolationCode::UnresolvedRequired, 'el-1', 'target', 'message'),
        ]));

        $gate = new LayoutBindingGate($registry, $resolvability, new ViolationConstraintMapper());

        $violations = $gate->bindingViolations(Uuid::fromHexToBytes($hex), [], Context::createDefaultContext());

        static::assertCount(1, $violations);
    }

    /**
     * @param EntitySearchResult<EntityCollection<Entity>> $result
     */
    private function registryReturning(EntitySearchResult $result): DefinitionInstanceRegistry
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($result);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturn($repository);

        return $registry;
    }

    /**
     * @return EntitySearchResult<EntityCollection<Entity>>
     */
    private function searchResult(?ContentLayoutEntity $layout): EntitySearchResult
    {
        $result = static::createStub(EntitySearchResult::class);
        $result->method('first')->willReturn($layout);

        return $result;
    }
}
