<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\Layout\Type\PrimitiveDefaultProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PrimitiveDefaultProvider::class)]
class PrimitiveDefaultProviderTest extends TestCase
{
    #[TestDox('returns only primitives with a non-null default, skipping defaultless primitives and references')]
    public function testForTypeSkipsNullDefaultsAndReferences(): void
    {
        $specs = [
            'Sw:Mixed' => ContentSystemElementTypeSpecificationBuilder::create('Sw:Mixed')
                ->primitive('withDefault', 'string', default: 'seeded')
                ->primitive('noDefault', 'string', required: true)
                ->reference('product', SalesChannelProductEntity::class)
                ->build(),
        ];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]);

        static::assertSame(['withDefault' => 'seeded'], (new PrimitiveDefaultProvider())->forType($registry, 'Sw:Mixed'));
    }
}
