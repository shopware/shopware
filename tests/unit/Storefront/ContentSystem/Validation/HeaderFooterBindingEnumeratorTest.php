<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\ContentSystem\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
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
    public function testEnumeratesBindings(bool $headerAssigned, bool $footerAssigned, array $expectedSources): void
    {
        $enumerator = new HeaderFooterBindingEnumerator(
            $this->repositoryWith($headerAssigned ? Uuid::randomHex() : null),
            $this->repositoryWith($footerAssigned ? Uuid::randomHex() : null),
        );

        $bindings = $enumerator->enumerate(Uuid::randomHex(), Context::createDefaultContext());

        static::assertSame(
            array_map(static fn (string $source): array => ['sourceId' => $source, 'providedRootContext' => []], $expectedSources),
            array_map(static fn ($binding): array => ['sourceId' => $binding->sourceId, 'providedRootContext' => $binding->providedRootContext], $bindings),
        );
    }

    /**
     * @return iterable<string, array{bool, bool, list<string>}>
     */
    public static function emitsBindingsProvider(): iterable
    {
        yield 'a header assignment only' => [true, false, ['header']];
        yield 'a footer assignment only' => [false, true, ['footer']];
        yield 'both a header and footer assignment' => [true, true, ['header', 'footer']];
        yield 'no assignment' => [false, false, []];
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function repositoryWith(?string $firstId): EntityRepository
    {
        $ids = static::createStub(IdSearchResult::class);
        $ids->method('firstId')->willReturn($firstId);

        $repository = static::createStub(EntityRepository::class);
        $repository->method('searchIds')->willReturn($ids);

        return $repository;
    }
}
