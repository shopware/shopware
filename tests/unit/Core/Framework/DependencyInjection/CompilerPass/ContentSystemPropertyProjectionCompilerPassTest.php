<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ContentSystem\Mapping\ProductMediaCollectionToMediaCollectionProjection;
use Shopware\Core\Content\Product\ContentSystem\Mapping\ProductMediaToMediaProjection;
use Shopware\Core\Framework\ContentSystem\Mapping\Projection\AbstractContentPropertyProjection;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\ContentSystemPropertyProjectionCompilerPass;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StubUppercaseProjection;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemPropertyProjectionCompilerPass::class)]
class ContentSystemPropertyProjectionCompilerPassTest extends TestCase
{
    #[TestDox('accepts the core projections, which declare distinct names and resolvable types')]
    public function testAcceptsTheCoreProjections(): void
    {
        $container = $this->containerWith(
            ProductMediaToMediaProjection::class,
            ProductMediaCollectionToMediaCollectionProjection::class,
            StubUppercaseProjection::class,
        );

        $this->expectNotToPerformAssertions();

        (new ContentSystemPropertyProjectionCompilerPass())->process($container);
    }

    /**
     * The name is stored on every layout mapping through it, so which of two claimants wins would decide what
     * those layouts render — by service registration order.
     */
    public function testRejectsTwoProjectionsClaimingTheSameName(): void
    {
        $container = $this->containerWith(StubUppercaseProjection::class, DuplicateNameProjection::class);

        $this->expectExceptionObject(DependencyInjectionException::propertyProjectionDuplicateName(
            DuplicateNameProjection::class,
            StubUppercaseProjection::class,
            StubUppercaseProjection::NAME,
        ));

        (new ContentSystemPropertyProjectionCompilerPass())->process($container);
    }

    public function testRejectsAnInputTypeThatIsNeitherAClassNorAPrimitive(): void
    {
        $container = $this->containerWith(UnresolvableInputTypeProjection::class);

        $this->expectExceptionObject(DependencyInjectionException::propertyProjectionInvalidType(
            UnresolvableInputTypeProjection::class,
            'input',
            'Shopware\\Nope\\NotAClass',
            ['string', 'integer', 'number', 'boolean'],
        ));

        (new ContentSystemPropertyProjectionCompilerPass())->process($container);
    }

    public function testRejectsAnOutputTypeThatIsNeitherAClassNorAPrimitive(): void
    {
        $container = $this->containerWith(UnresolvableOutputTypeProjection::class);

        $this->expectExceptionObject(DependencyInjectionException::propertyProjectionInvalidType(
            UnresolvableOutputTypeProjection::class,
            'output',
            'collection<Media>',
            ['string', 'integer', 'number', 'boolean'],
        ));

        (new ContentSystemPropertyProjectionCompilerPass())->process($container);
    }

    public function testRejectsATaggedServiceThatIsNotAProjection(): void
    {
        $container = $this->containerWith(\stdClass::class);

        $this->expectException(DependencyInjectionException::class);
        $this->expectExceptionMessage(AbstractContentPropertyProjection::class);

        (new ContentSystemPropertyProjectionCompilerPass())->process($container);
    }

    public function testSkipsATaggedServiceWithNoResolvableClass(): void
    {
        $container = new ContainerBuilder();
        $definition = new Definition();
        $definition->addTag('content_system.property_projection');
        $container->setDefinition('app.classless_projection', $definition);

        $this->expectNotToPerformAssertions();

        (new ContentSystemPropertyProjectionCompilerPass())->process($container);
    }

    /**
     * An abstract definition is a parent template rather than a service, so there is no projection to judge.
     */
    public function testSkipsAnAbstractDefinition(): void
    {
        $container = new ContainerBuilder();
        $definition = new Definition(DuplicateNameProjection::class);
        $definition->setAbstract(true);
        $definition->addTag('content_system.property_projection');
        $container->setDefinition('app.abstract_projection', $definition);
        $container->setDefinition(StubUppercaseProjection::class, $this->tagged(StubUppercaseProjection::class));

        $this->expectNotToPerformAssertions();

        (new ContentSystemPropertyProjectionCompilerPass())->process($container);
    }

    /**
     * @param class-string ...$classes
     */
    private function containerWith(string ...$classes): ContainerBuilder
    {
        $container = new ContainerBuilder();

        foreach ($classes as $class) {
            $container->setDefinition($class, $this->tagged($class));
        }

        return $container;
    }

    /**
     * @param class-string $class
     */
    private function tagged(string $class): Definition
    {
        $definition = new Definition($class);
        $definition->addTag('content_system.property_projection');

        return $definition;
    }
}

/**
 * @internal
 */
#[Package('framework')]
class DuplicateNameProjection extends AbstractContentPropertyProjection
{
    public function name(): string
    {
        return StubUppercaseProjection::NAME;
    }

    public function inputType(): string
    {
        return 'string';
    }

    public function outputType(): string
    {
        return 'string';
    }

    public function project(mixed $value): mixed
    {
        return $value;
    }
}

/**
 * @internal
 */
#[Package('framework')]
class UnresolvableInputTypeProjection extends AbstractContentPropertyProjection
{
    public function name(): string
    {
        return 'unresolvable_input';
    }

    public function inputType(): string
    {
        return 'Shopware\\Nope\\NotAClass';
    }

    public function outputType(): string
    {
        return 'string';
    }

    public function project(mixed $value): mixed
    {
        return $value;
    }
}

/**
 * @internal
 */
#[Package('framework')]
class UnresolvableOutputTypeProjection extends AbstractContentPropertyProjection
{
    public function name(): string
    {
        return 'unresolvable_output';
    }

    public function inputType(): string
    {
        return 'string';
    }

    public function outputType(): string
    {
        return 'collection<Media>';
    }

    public function project(mixed $value): mixed
    {
        return $value;
    }
}
