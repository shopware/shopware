<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\ContentSystem\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Storefront\ContentSystem\Validation\HeaderFooterBindingEnumerator;

/**
 * @internal
 */
#[CoversClass(HeaderFooterBindingEnumerator::class)]
class HeaderFooterBindingEnumeratorTest extends TestCase
{
    /**
     * @param list<string> $expectedSources
     */
    #[DataProvider('emitsBindingsProvider')]
    #[TestDox('enumerates the source bindings for $_dataName each with an empty provided root context')]
    public function testEnumeratesBindings(?string $headerId, ?string $footerId, array $expectedSources): void
    {
        $enumerator = new HeaderFooterBindingEnumerator(
            $this->repositoryWith($headerId),
            $this->repositoryWith($footerId),
        );

        $bindings = $enumerator->enumerate(Uuid::randomHex(), Context::createDefaultContext());

        static::assertSame(
            array_map(static fn (string $source): array => ['sourceId' => $source, 'providedRootContext' => []], $expectedSources),
            array_map(static fn ($binding): array => ['sourceId' => $binding->sourceId, 'providedRootContext' => $binding->providedRootContext], $bindings),
        );
    }

    /**
     * @return iterable<string, array{?string, ?string, list<string>}>
     */
    public static function emitsBindingsProvider(): iterable
    {
        yield 'a header assignment only' => ['header-layout', null, ['header']];
        yield 'a footer assignment only' => [null, 'footer-layout', ['footer']];
        yield 'both a header and footer assignment' => ['header-layout', 'footer-layout', ['header', 'footer']];
        yield 'no assignment' => [null, null, []];
    }

    /**
     * @return StaticEntityRepository<EntityCollection<Entity>>
     */
    private function repositoryWith(?string $firstId): StaticEntityRepository
    {
        // searchIds() shifts the next queue entry; a flat id list builds the IdSearchResult, an empty list yields firstId() === null.
        /** @var StaticEntityRepository<EntityCollection<Entity>> $repository */
        $repository = new StaticEntityRepository([$firstId !== null ? [$firstId] : []]);

        return $repository;
    }
}
