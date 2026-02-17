<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Event\Listener\PreHydration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Event\Listener\PreHydration\RedistributeExpansionSubscriber;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\ContentElementBuilder;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\EventFactory;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(RedistributeExpansionSubscriber::class)]
class RedistributeExpansionSubscriberTest extends TestCase
{
    private RedistributeExpansionSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new RedistributeExpansionSubscriber();
    }

    #[TestDox('generates virtual broadcast provider for consumer with redistribute flag')]
    public function testGeneratesVirtualBroadcastProviderForRedistributeConsumer(): void
    {
        $element = ContentElementBuilder::create('section', 'e1')
            ->withConsumer('product', ContextType::Single, required: false, redistribute: true)
            ->build();

        $event = EventFactory::preHydration([$element]);
        $this->subscriber->__invoke($event);

        $providers = $element->getProvidesContext();
        static::assertArrayHasKey('product', $providers);
    }

    #[TestDox('does not generate provider when redistribute flag is false')]
    public function testDoesNotGenerateProviderWhenRedistributeIsFalse(): void
    {
        $element = ContentElementBuilder::create('section', 'e1')
            ->withConsumer('product', ContextType::Single, required: false, redistribute: false)
            ->build();

        $event = EventFactory::preHydration([$element]);
        $this->subscriber->__invoke($event);

        $providers = $element->getProvidesContext();
        static::assertEmpty($providers);
    }

    #[TestDox('throws when redistribute consumer has dotted context path')]
    public function testThrowsWhenRedistributeConsumerHasDottedPath(): void
    {
        $element = ContentElementBuilder::create('section', 'e1')
            ->withConsumer('product.cover', ContextType::Single, required: false, redistribute: true)
            ->build();

        $event = EventFactory::preHydration([$element]);

        static::expectExceptionObject(ContentSystemException::redistributeWithDottedPath('product.cover'));

        $this->subscriber->__invoke($event);
    }

    #[TestDox('throws when virtual provider conflicts with existing provider')]
    public function testThrowsWhenVirtualProviderConflictsWithExistingProvider(): void
    {
        $element = ContentElementBuilder::create('section', 'e1')
            ->withConsumer('product', ContextType::Single, required: false, redistribute: true)
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->build();

        $event = EventFactory::preHydration([$element]);

        static::expectExceptionObject(ContentSystemException::redistributeConflict('product'));

        $this->subscriber->__invoke($event);
    }

    #[TestDox('throws on property alias collision within the same element')]
    public function testThrowsOnPropertyAliasCollision(): void
    {
        $element = ContentElementBuilder::create('section', 'e1')
            ->withConsumer('product', ContextType::Single, required: false, propertyAlias: 'data')
            ->withConsumer('category', ContextType::Single, required: false, propertyAlias: 'data')
            ->build();

        $event = EventFactory::preHydration([$element]);

        static::expectExceptionObject(ContentSystemException::propertyAliasCollision('data', 'product', 'category'));

        $this->subscriber->__invoke($event);
    }

    #[TestDox('expands redistribute recursively into nested elements')]
    public function testExpandsRecursivelyIntoNestedElements(): void
    {
        $child = ContentElementBuilder::create('child', 'c1')
            ->withConsumer('product', ContextType::Single, required: false, redistribute: true)
            ->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withConsumer('product', ContextType::Single, required: false, redistribute: true)
            ->withSlot('default', [$child])
            ->build();

        $event = EventFactory::preHydration([$parent]);
        $this->subscriber->__invoke($event);

        static::assertArrayHasKey('product', $parent->getProvidesContext());
        static::assertArrayHasKey('product', $child->getProvidesContext());
    }

    #[TestDox('uses consumer alias as provider key when set')]
    public function testUsesConsumerAliasAsProviderKeyWhenSet(): void
    {
        $element = ContentElementBuilder::create('section', 'e1')
            ->withConsumer('product', ContextType::Single, required: false, redistribute: true, consumerAlias: 'myProduct')
            ->build();

        $event = EventFactory::preHydration([$element]);
        $this->subscriber->__invoke($event);

        $providers = $element->getProvidesContext();
        static::assertArrayHasKey('myProduct', $providers);
        static::assertArrayNotHasKey('product', $providers);
    }
}
