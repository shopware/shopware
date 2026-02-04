<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeEntityDefinition;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\AttributeEntityTagCheckCompilerPass;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[CoversClass(AttributeEntityTagCheckCompilerPass::class)]
class AttributeEntityTagCheckCompilerPassTest extends TestCase
{
    public function testPassesWhenEntityTagAttributePresent(): void
    {
        $container = new ContainerBuilder();
        $container
            ->register('test_entity.definition', AttributeEntityDefinition::class)
            ->addTag('shopware.entity.definition', ['entity' => 'test_entity']);

        $pass = new AttributeEntityTagCheckCompilerPass();
        $pass->process($container);

        $this->expectNotToPerformAssertions();
    }

    /**
     * @param array<string, string> $tagAttributes
     */
    #[DataProvider('invalidTagAttributesProvider')]
    public function testThrowsExceptionWhenEntityTagAttributeInvalid(array $tagAttributes): void
    {
        $container = new ContainerBuilder();
        $container
            ->register('test_entity.definition', AttributeEntityDefinition::class)
            ->addTag('shopware.entity.definition', $tagAttributes);

        $this->expectException(DependencyInjectionException::class);
        $this->expectExceptionMessage('missing the "entity" attribute');

        $pass = new AttributeEntityTagCheckCompilerPass();
        $pass->process($container);
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function invalidTagAttributesProvider(): iterable
    {
        yield 'missing attribute' => [[]];
        yield 'empty string' => [['entity' => '']];
    }

    #[DataProvider('skippedDefinitionsProvider')]
    public function testSkipsNonAttributeBasedDefinitions(?string $class): void
    {
        $container = new ContainerBuilder();
        $definition = $container->register('test.definition');
        $definition->addTag('shopware.entity.definition');

        if ($class !== null) {
            $definition->setClass($class);
        }

        $pass = new AttributeEntityTagCheckCompilerPass();
        $pass->process($container);

        $this->expectNotToPerformAssertions();
    }

    /**
     * @return iterable<string, array{class-string|null}>
     */
    public static function skippedDefinitionsProvider(): iterable
    {
        yield 'null class' => [null];
        yield 'non-AttributeBasedEntityDefinition' => [ProductDefinition::class];
    }
}
