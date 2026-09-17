<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mapping\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\Provider\AbstractMappingCandidateProvider;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\ContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemMappingCandidateRegistry::class)]
class ContentSystemMappingCandidateRegistryTest extends TestCase
{
    public function testAggregatesOnlyTheProvidersThatClaimTheRootSource(): void
    {
        $registry = new ContentSystemMappingCandidateRegistry([
            new StaticMappingCandidateProvider(['category'], [self::candidate('category.name')]),
            new StaticMappingCandidateProvider(['product'], [self::candidate('product.name')]),
        ]);

        static::assertSame(['category.name'], array_keys($registry->forRootSource('category')));
    }

    public function testKeysTheCatalogueByPathSoTheWriteGateCanLookOneUp(): void
    {
        $candidate = self::candidate('category.name');

        $registry = new ContentSystemMappingCandidateRegistry([
            new StaticMappingCandidateProvider(['category'], [$candidate]),
        ]);

        static::assertSame($candidate, $registry->forRootSource('category')['category.name']);
    }

    public function testMergesEveryClaimingProviderIntoOneCatalogue(): void
    {
        $registry = new ContentSystemMappingCandidateRegistry([
            new StaticMappingCandidateProvider(['category'], [self::candidate('category.name')]),
            new StaticMappingCandidateProvider(['category', 'product'], [self::candidate('category.customField')]),
        ]);

        static::assertSame(
            ['category.name', 'category.customField'],
            array_keys($registry->forRootSource('category'))
        );
    }

    public function testTheFirstProviderToOfferAPathKeepsIt(): void
    {
        $specific = self::candidate('category.name', label: 'the specific one');
        $crossCutting = self::candidate('category.name', label: 'the overlapping one');

        $registry = new ContentSystemMappingCandidateRegistry([
            new StaticMappingCandidateProvider(['category'], [$specific]),
            new StaticMappingCandidateProvider(['category'], [$crossCutting]),
        ]);

        static::assertSame($specific, $registry->forRootSource('category')['category.name']);
    }

    public function testARootSourceNobodyClaimsYieldsAnEmptyCatalogueRatherThanThrowing(): void
    {
        $registry = new ContentSystemMappingCandidateRegistry([
            new StaticMappingCandidateProvider(['category'], [self::candidate('category.name')]),
        ]);

        static::assertSame([], $registry->forRootSource('none'));
    }

    public function testGetDecoratedThrowsOnTheLeaf(): void
    {
        $registry = new ContentSystemMappingCandidateRegistry([]);

        $this->expectExceptionObject(new DecorationPatternException(ContentSystemMappingCandidateRegistry::class));

        $registry->getDecorated();
    }

    private static function candidate(string $path, string $label = 'a label'): MappingCandidate
    {
        return new MappingCandidate(
            path: $path,
            label: $label,
            description: 'a description',
            group: 'basic',
            valueType: 'string',
        );
    }
}

/**
 * @internal
 */
class StaticMappingCandidateProvider extends AbstractMappingCandidateProvider
{
    /**
     * @param list<string> $rootSources
     * @param list<MappingCandidate> $candidates
     */
    public function __construct(
        private readonly array $rootSources,
        private readonly array $candidates,
    ) {
    }

    public function supports(string $rootSource): bool
    {
        return \in_array($rootSource, $this->rootSources, true);
    }

    public function provide(string $rootSource): array
    {
        return $this->candidates;
    }
}
