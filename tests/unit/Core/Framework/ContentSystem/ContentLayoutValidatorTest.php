<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentLayoutValidator;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;

/**
 * @internal
 */
#[CoversClass(ContentLayoutValidator::class)]
class ContentLayoutValidatorTest extends TestCase
{
    #[TestDox('returns no violations when every component in the tree is registered')]
    public function testReturnsNoViolationsForFullyRegisteredTree(): void
    {
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturn(true);

        $validator = new ContentLayoutValidator($registry);

        $tree = ContentElementBuilder::create('Sw:Layout:Section', 'root')
            ->withSlot('content', [ContentElementBuilder::create('Sw:Content:Heading', 'child')->build()])
            ->build();

        static::assertCount(0, $validator->validate([$tree]));
    }

    #[TestDox('reports a violation for an unregistered component nested below a registered root')]
    public function testReportsViolationForUnregisteredNestedComponent(): void
    {
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnMap([
            ['Sw:Layout:Section', true],
            ['Sw:Unknown:Widget', false],
        ]);

        $validator = new ContentLayoutValidator($registry);

        $tree = ContentElementBuilder::create('Sw:Layout:Section', 'root')
            ->withSlot('content', [ContentElementBuilder::create('Sw:Unknown:Widget', 'bad-child')->build()])
            ->build();

        $violations = $validator->validate([$tree]);

        static::assertCount(1, $violations);
        static::assertSame('bad-child', $violations->get(0)->getPropertyPath());
    }

    #[TestDox('collects violations across multiple root elements without throwing')]
    public function testCollectsViolationsAcrossMultipleRoots(): void
    {
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturn(false);

        $validator = new ContentLayoutValidator($registry);

        $roots = [
            ContentElementBuilder::create('Sw:Unknown:A', 'root-a')->build(),
            ContentElementBuilder::create('Sw:Unknown:B', 'root-b')->build(),
        ];

        static::assertCount(2, $validator->validate($roots));
    }
}
