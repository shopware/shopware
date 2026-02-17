<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Hydration\DataContext;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\DataContextResolver;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\ContentElementBuilder;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(DataContextResolver::class)]
class DataContextResolverTest extends TestCase
{
    #[TestDox('distributes context data from provider to consumer in a full tree')]
    public function testResolveDistributesContextInFullTree(): void
    {
        $child = ContentElementBuilder::create('text', 'child-id')
            ->withConsumer('product', ContextType::Single, required: false)
            ->build();

        $parent = ContentElementBuilder::create('section', 'parent-id')
            ->withProperty('product', 'some-data')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('default', [$child])
            ->build();

        $resolver = new DataContextResolver(new ContextPathResolver());
        $resolver->resolve($parent);

        static::assertSame('some-data', $child->getProperty('product'));
    }

    #[TestDox('does not distribute context when provider data is null')]
    public function testResolveDoesNotDistributeWhenProviderDataIsNull(): void
    {
        $child = ContentElementBuilder::create('text', 'child-id')
            ->withConsumer('product', ContextType::Single, required: false)
            ->build();

        $parent = ContentElementBuilder::create('section', 'parent-id')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('default', [$child])
            ->build();

        $resolver = new DataContextResolver(new ContextPathResolver());
        $resolver->resolve($parent);

        static::assertNull($child->getProperty('product'));
    }

    #[TestDox('does not set consumer property when no matching provider exists in the tree')]
    public function testResolveDoesNotSetPropertyWhenNoMatchingProviderExists(): void
    {
        $element = ContentElementBuilder::create('text', 'consumer-id')
            ->withConsumer('product', ContextType::Single, required: false)
            ->build();

        $resolver = new DataContextResolver(new ContextPathResolver());
        $resolver->resolve($element);

        static::assertNull($element->getProperty('product'));
    }

    #[TestDox('throws when required sub-path consumer receives non-Struct data')]
    public function testResolveThrowsWhenRequiredSubPathConsumerReceivesNonStructData(): void
    {
        $child = ContentElementBuilder::create('text', 'child-id')
            ->withConsumer('product.name', ContextType::Single, required: true)
            ->build();

        $parent = ContentElementBuilder::create('section', 'parent-id')
            ->withProperty('product', 'not-a-struct')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('default', [$child])
            ->build();

        $resolver = new DataContextResolver(new ContextPathResolver());

        static::expectExceptionObject(ContentSystemException::contextPathNotResolvable(
            'product.name',
            'child-id',
            'Context data is not a Struct instance'
        ));

        $resolver->resolve($parent);
    }
}
