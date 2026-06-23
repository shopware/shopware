<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Visitor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Visitor\DefaultSeedingVisitor;
use Shopware\Core\Framework\ContentSystem\Layout\Type\PrimitiveDefaultProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;

/**
 * @internal
 */
#[CoversClass(DefaultSeedingVisitor::class)]
class DefaultSeedingVisitorTest extends TestCase
{
    #[TestDox('seeds a missing primitive default on enter')]
    public function testEnterSeedsDefaultWhenPropertyIsMissing(): void
    {
        $element = new ContentElement('fresh', 'Sw:Block');

        $this->visitor()->enter($element);

        static::assertSame('Default headline', $element->getProperty('headline'));
    }

    #[TestDox('keeps an authored value on enter')]
    public function testEnterKeepsAuthoredValueWhenPropertyIsPresent(): void
    {
        $element = new ContentElement('authored', 'Sw:Block', [], ['headline' => 'Authored']);

        $this->visitor()->enter($element);

        static::assertSame('Authored', $element->getProperty('headline'));
    }

    #[TestDox('leaves an unregistered component untouched on enter')]
    public function testEnterNoOpsOnUnregisteredComponent(): void
    {
        $element = new ContentElement('el', 'Sw:Unregistered');

        $this->visitor()->enter($element);

        static::assertSame([], $element->getProperties());
    }

    #[TestDox('seeds the whole subtree when driven by traverse')]
    public function testTraverseSeedsSubtree(): void
    {
        $child = new ContentElement('child', 'Sw:Block');
        $root = new ContentElement('root', 'Sw:Block', [], [], ['content' => new SlotContent([$child])]);

        $root->traverse($this->visitor());

        static::assertSame('Default headline', $root->getProperty('headline'));
        static::assertSame('Default headline', $child->getProperty('headline'));
    }

    private function visitor(): DefaultSeedingVisitor
    {
        $specs = [
            'Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create('Sw:Block')
                ->primitive('headline', 'string', default: 'Default headline')
                ->build(),
        ];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => isset($specs[$name]));
        $registry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]);

        return new DefaultSeedingVisitor($registry, new PrimitiveDefaultProvider());
    }
}
