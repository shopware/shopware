<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mapping\Projection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Mapping\Projection\AbstractContentPropertyProjection;
use Shopware\Core\Framework\ContentSystem\Mapping\Projection\ContentSystemPropertyProjectionRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Test\Stub\ContentSystem\StubUppercaseProjection;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemPropertyProjectionRegistry::class)]
class ContentSystemPropertyProjectionRegistryTest extends TestCase
{
    public function testIndexesTheTaggedProjectionsByName(): void
    {
        $projection = new StubUppercaseProjection();

        $registry = new ContentSystemPropertyProjectionRegistry([$projection]);

        static::assertSame([StubUppercaseProjection::NAME => $projection], $registry->all());
        static::assertSame($projection, $registry->get(StubUppercaseProjection::NAME));
    }

    public function testAnswersNullForANameNothingIsRegisteredUnder(): void
    {
        $registry = new ContentSystemPropertyProjectionRegistry([new StubUppercaseProjection()]);

        static::assertNull($registry->get('nothing_registered_here'));
    }

    public function testAnEmptyContainerYieldsAnEmptyCatalogue(): void
    {
        static::assertSame([], (new ContentSystemPropertyProjectionRegistry([]))->all());
    }

    /**
     * The tagged iterator is a generator, so a second read would otherwise come back empty.
     */
    #[TestDox('reads the tagged iterator once and answers from the index afterwards')]
    public function testMemoizesTheIndexAcrossReads(): void
    {
        $registry = new ContentSystemPropertyProjectionRegistry(
            (static function (): \Generator {
                yield new StubUppercaseProjection();
            })()
        );

        static::assertCount(1, $registry->all());
        static::assertCount(1, $registry->all());
        static::assertInstanceOf(AbstractContentPropertyProjection::class, $registry->get(StubUppercaseProjection::NAME));
    }

    public function testRefusesDecoration(): void
    {
        $this->expectExceptionObject(new DecorationPatternException(ContentSystemPropertyProjectionRegistry::class));

        (new ContentSystemPropertyProjectionRegistry([]))->getDecorated();
    }
}
