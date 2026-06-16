<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Visitor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Visitor\ComponentRegistrationVisitor;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;

/**
 * @internal
 */
#[CoversClass(ComponentRegistrationVisitor::class)]
class ComponentRegistrationVisitorTest extends TestCase
{
    #[TestDox('collects no violation for a registered component')]
    public function testRegisteredComponentProducesNoViolation(): void
    {
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturn(true);

        $visitor = new ComponentRegistrationVisitor($registry);

        $element = ContentElementBuilder::create('Sw:Content:Heading', 'elem-1')->build();
        $visitor->enter($element);
        $visitor->leave($element);

        static::assertCount(0, $visitor->getViolations());
    }

    #[TestDox('collects a violation naming the unregistered component on the element id')]
    public function testUnregisteredComponentProducesViolation(): void
    {
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturn(false);

        $visitor = new ComponentRegistrationVisitor($registry);

        $element = ContentElementBuilder::create('Sw:Unknown:Widget', 'elem-1')->build();
        $visitor->enter($element);
        $visitor->leave($element);

        $violations = $visitor->getViolations();
        static::assertCount(1, $violations);
        static::assertSame('elem-1', $violations->get(0)->getPropertyPath());
        static::assertStringContainsString('Sw:Unknown:Widget', (string) $violations->get(0)->getMessage());
    }

    #[TestDox('accumulates one violation per unregistered component across multiple entries')]
    public function testAccumulatesViolationsAcrossEntries(): void
    {
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnMap([
            ['Sw:Known:Block', true],
            ['Sw:Unknown:A', false],
            ['Sw:Unknown:B', false],
        ]);

        $visitor = new ComponentRegistrationVisitor($registry);

        foreach (['Sw:Known:Block', 'Sw:Unknown:A', 'Sw:Unknown:B'] as $index => $component) {
            $visitor->enter(ContentElementBuilder::create($component, 'elem-' . $index)->build());
        }

        static::assertCount(2, $visitor->getViolations());
    }
}
