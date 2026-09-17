<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mapping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingTypeCompatibility;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MappingTypeCompatibility::class)]
class MappingTypeCompatibilityTest extends TestCase
{
    /**
     * @param string|list<string> $declaredType
     */
    #[DataProvider('compatibilityProvider')]
    public function testPermits(string|array $declaredType, string $candidateValueType, bool $expected): void
    {
        static::assertSame($expected, (new MappingTypeCompatibility())->permits($declaredType, $candidateValueType));
    }

    public static function compatibilityProvider(): iterable
    {
        yield 'a primitive takes the same primitive' => ['string', 'string', true];

        yield 'a primitive rejects a different primitive, with no widening' => ['integer', 'string', false];

        // Both directions, because a one-sided guard would let the mismatch through on the other.
        yield 'a primitive rejects a class-typed candidate' => ['string', MediaEntity::class, false];

        yield 'a class-typed property rejects a primitive candidate' => [MediaEntity::class, 'string', false];

        yield 'an FQCN takes exactly its own class' => [MediaEntity::class, MediaEntity::class, true];

        // The sales-channel entity is what the render path actually delivers for a product, so a property
        // declaring the plain entity must accept it or every product mapping would be rejected.
        yield 'an FQCN takes a subclass, so a sales-channel entity fills a plain entity property' => [
            ProductEntity::class,
            SalesChannelProductEntity::class,
            true,
        ];

        yield 'an FQCN rejects its own superclass, which need not carry the members the property expects' => [
            SalesChannelProductEntity::class,
            ProductEntity::class,
            false,
        ];

        yield 'an FQCN rejects an unrelated class' => [MediaEntity::class, MediaCollection::class, false];

        yield 'a union is satisfied by any one member' => [['string', MediaEntity::class], MediaEntity::class, true];

        yield 'a union rejects a candidate matching no member' => [['string', 'integer'], MediaEntity::class, false];

        yield 'bare object takes any class-typed candidate' => ['object', MediaCollection::class, true];

        yield 'bare object rejects a primitive, which is not an object' => ['object', 'string', false];
    }
}
