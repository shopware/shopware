<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding\Registry;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\AbstractContentSystemBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\DatabaseBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\ContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ContentSystemBindingSpecificationRegistry::class)]
class ContentSystemBindingSpecificationRegistryTest extends TestCase
{
    #[TestDox('aggregates specifications from every loader keyed by source-qualified id')]
    public function testAggregatesSpecificationsFromAllLoadersKeyedByQualifiedId(): void
    {
        $registry = $this->registry([
            $this->loader($this->specification('from-media-library', 'media-gallery', 'core')),
            $this->loader($this->specification('from-product-list', 'product-grid', 'plugin:Acme')),
        ]);

        static::assertSame(
            ['core:from-media-library', 'plugin:Acme:from-product-list'],
            array_keys($registry->all())
        );
    }

    #[TestDox('returns only specifications matching the given type, as a list')]
    public function testByTypeFiltersByType(): void
    {
        $registry = $this->registry([
            $this->loader(
                $this->specification('from-media-library', 'media-gallery', 'core'),
                $this->specification('from-product-list', 'product-grid', 'core'),
                $this->specification('from-media-library-alt', 'media-gallery', 'plugin:Acme'),
            ),
        ]);

        $byType = $registry->byType('media-gallery');

        static::assertSame([0, 1], array_keys($byType));
        static::assertSame(['from-media-library', 'from-media-library-alt'], array_map(static fn (BindingSpecification $s) => $s->id(), $byType));
    }

    #[TestDox('get resolves a specification by its source-qualified id')]
    public function testGetResolvesBySourceQualifiedId(): void
    {
        $registry = $this->registry([
            $this->loader($this->specification('from-media-library', 'media-gallery', 'core')),
        ]);

        $specification = $registry->get('core:from-media-library');

        static::assertNotNull($specification);
        static::assertSame('from-media-library', $specification->id());
    }

    #[TestDox('returns an empty list when no specification matches the type')]
    public function testByTypeReturnsEmptyListForUnmatchedType(): void
    {
        $registry = $this->registry([
            $this->loader($this->specification('from-media-library', 'media-gallery', 'core')),
        ]);

        static::assertSame([], $registry->byType('unknown-type'));
    }

    #[TestDox('get returns null for an id that does not exist')]
    public function testGetReturnsNullForMissingId(): void
    {
        $registry = $this->registry([
            $this->loader($this->specification('from-media-library', 'media-gallery', 'core')),
        ]);

        static::assertNull($registry->get('missing:x'));
    }

    #[TestDox('throws when unwrapped as a decorator')]
    public function testGetDecoratedThrows(): void
    {
        $this->expectExceptionObject(new DecorationPatternException(ContentSystemBindingSpecificationRegistry::class));

        $this->registry([])->getDecorated();
    }

    #[TestDox('throws when invalidate is called on the leaf registry, per the decoration-pattern contract')]
    public function testInvalidateOnLeafRegistryThrows(): void
    {
        // invalidate() is defined on the abstract base (self::class), inherited unchanged by the leaf;
        // only the cached decorator overrides it. So the exception names the abstract base class.
        $this->expectExceptionObject(new DecorationPatternException(AbstractContentSystemBindingSpecificationRegistry::class));

        $this->registry([])->invalidate();
    }

    #[TestDox('throws bindingSpecificationDuplicate when two loaders emit the same source-qualified id')]
    public function testThrowsOnCrossLoaderQualifiedIdCollision(): void
    {
        $registry = $this->registry([
            $this->loader($this->specification('dup', 'Sw:Product', 'app:Acme')),
            $this->loader($this->specification('dup', 'Sw:Product', 'app:Acme')),
        ]);

        try {
            $registry->all();
            static::fail('Expected ContentSystemException.');
        } catch (ContentSystemException $e) {
            static::assertSame(ContentSystemException::BINDING_SPECIFICATION_DUPLICATE, $e->getErrorCode());
        }
    }

    #[TestDox('promoted backstop: an authored (YAML) promoted flag beats a persisted (DB) one, demoting the DB flag and logging a warning')]
    public function testPromotedBackstopAuthoredBeatsDatabase(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(static::stringContains('app:Acme:from-db'));

        $registry = $this->registry([
            $this->loader($this->specification('from-yaml', 'Sw:Media:Image', 'core', true)),
            $this->databaseLoader([$this->promotedRow('Acme', 'from-db', 'Sw:Media:Image')]),
        ], $logger);

        $all = $registry->all();

        static::assertTrue($all['core:from-yaml']->isPromoted());
        static::assertFalse($all['app:Acme:from-db']->isPromoted());
    }

    #[TestDox('promoted backstop: within one origin class the lexicographically smallest source-qualified id wins, demoting the rest')]
    public function testPromotedBackstopLexicographicTiebreakWithinOrigin(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(static::stringContains('core:beta'));

        // Both specifications come from authored loaders (same origin class), so the tiebreak is purely
        // lexicographic on the qualified id: "core:alpha" wins over "core:beta".
        $registry = $this->registry([
            $this->loader(
                $this->specification('beta', 'Sw:Media:Image', 'core', true),
                $this->specification('alpha', 'Sw:Media:Image', 'core', true),
            ),
        ], $logger);

        $all = $registry->all();

        static::assertTrue($all['core:alpha']->isPromoted());
        static::assertFalse($all['core:beta']->isPromoted());
    }

    #[TestDox('promoted backstop: between two persisted (DB) promoted flags the lexicographically smallest source-qualified id wins, demoting the rest')]
    public function testPromotedBackstopDbVsDbLexicographicTiebreak(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(static::stringContains('app:Zeta:first-loaded'));

        // Two rows through ONE DatabaseBindingSpecificationLoader: the app-vs-app conflict the backstop exists
        // for (install-then-activate ordering bypasses the app validator). Load order must not decide, so the
        // row loaded first loses to the lexicographically smaller qualified id.
        $registry = $this->registry([
            $this->databaseLoader([
                $this->promotedRow('Zeta', 'first-loaded', 'Sw:Media:Image'),
                $this->promotedRow('Acme', 'later-loaded', 'Sw:Media:Image'),
            ]),
        ], $logger);

        $all = $registry->all();

        static::assertTrue($all['app:Acme:later-loaded']->isPromoted());
        static::assertFalse($all['app:Zeta:first-loaded']->isPromoted());
    }

    #[TestDox('promoted backstop: a single promoted specification for a type passes through untouched, with no warning')]
    public function testPromotedBackstopSinglePromotedPassesThrough(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $registry = $this->registry([
            $this->loader(
                $this->specification('promoted', 'Sw:Media:Image', 'core', true),
                $this->specification('other', 'Sw:Media:Image', 'core', false),
            ),
        ], $logger);

        $all = $registry->all();

        static::assertTrue($all['core:promoted']->isPromoted());
        static::assertFalse($all['core:other']->isPromoted());
    }

    #[TestDox('promoted backstop: an aggregate with no promoted specification passes through untouched, with no warning')]
    public function testPromotedBackstopZeroPromotedPassesThrough(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $registry = $this->registry([
            $this->loader(
                $this->specification('a', 'Sw:Media:Image', 'core', false),
                $this->specification('b', 'Sw:Media:Image', 'plugin:Acme', false),
            ),
        ], $logger);

        $all = $registry->all();

        static::assertFalse($all['core:a']->isPromoted());
        static::assertFalse($all['plugin:Acme:b']->isPromoted());
    }

    #[TestDox('promoted backstop: two promoted specifications for DIFFERENT types both survive untouched')]
    public function testPromotedBackstopDifferentTypesBothSurvive(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $registry = $this->registry([
            $this->loader(
                $this->specification('media', 'Sw:Media:Image', 'core', true),
                $this->specification('product', 'Sw:Product:Box', 'core', true),
            ),
        ], $logger);

        $all = $registry->all();

        static::assertTrue($all['core:media']->isPromoted());
        static::assertTrue($all['core:product']->isPromoted());
    }

    private function specification(string $id, string $type, string $source, bool $promoted = false): BindingSpecification
    {
        return new BindingSpecification($id, $type, 'label', [], [], $source, $promoted);
    }

    /**
     * @param list<AbstractContentSystemBindingSpecificationLoader> $loaders
     */
    private function registry(array $loaders, ?LoggerInterface $logger = null): ContentSystemBindingSpecificationRegistry
    {
        return new ContentSystemBindingSpecificationRegistry($loaders, $logger ?? new NullLogger());
    }

    private function loader(BindingSpecification ...$specifications): AbstractContentSystemBindingSpecificationLoader
    {
        return new class(array_values($specifications)) extends AbstractContentSystemBindingSpecificationLoader {
            /**
             * @param list<BindingSpecification> $specifications
             */
            public function __construct(private readonly array $specifications)
            {
            }

            public function load(): array
            {
                return $this->specifications;
            }
        };
    }

    /**
     * A real DatabaseBindingSpecificationLoader (extending its @final class in a test is disallowed) fed canned
     * persisted rows, so it classifies as a DB-origin loader for the backstop's winner rule.
     *
     * @param list<array<string, mixed>> $rows
     */
    private function databaseLoader(array $rows): DatabaseBindingSpecificationLoader
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($rows);

        $validator = static::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        return new DatabaseBindingSpecificationLoader(
            'prod',
            $connection,
            static::createStub(LoggerInterface::class),
            new BindingSpecificationSerializer(),
            $validator,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function promotedRow(string $appName, string $name, string $type): array
    {
        return [
            'app_name' => $appName,
            'name' => $name,
            'schema' => json_encode(['type' => $type, 'label' => 'x', 'promoted' => true, 'resolves' => [], 'inputs' => []]),
        ];
    }
}
