<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\Aggregate\CategoryContentLayout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryContentLayout\CategoryContentLayoutDefinition;
use Shopware\Core\Content\Category\Aggregate\CategoryContentLayout\CategoryMappingCandidateProvider;
use Shopware\Core\Content\Category\SalesChannel\SalesChannelCategoryEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\ContentSystem\Helper\ContentLayoutMetadataDeriver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CategoryMappingCandidateProvider::class)]
class CategoryMappingCandidateProviderTest extends TestCase
{
    private CategoryMappingCandidateProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new CategoryMappingCandidateProvider(
            new CategoryContentLayoutDefinition(new ContentLayoutMetadataDeriver())
        );
    }

    public function testClaimsTheCategoryRootSourceOnly(): void
    {
        static::assertTrue($this->provider->supports('category'));
        static::assertFalse($this->provider->supports('product'));
        static::assertFalse($this->provider->supports('none'));
    }

    public function testOffersTheCategoryNameAsAStringCandidate(): void
    {
        $candidate = $this->candidateFor('category.name');

        static::assertSame('string', $candidate->valueType);
        static::assertSame(ContextType::Single, $candidate->contextType);
        static::assertNull($candidate->projection);
        static::assertSame('basic', $candidate->group);
    }

    public function testOffersTheCategoryImageAsAMediaEntityCandidate(): void
    {
        $candidate = $this->candidateFor('category.media');

        static::assertSame(MediaEntity::class, $candidate->valueType);
        static::assertSame('media', $candidate->group);
    }

    /**
     * The invariant the whole catalogue rests on. `ContextPathResolver` walks `Struct::getVars()` and needs a
     * `Struct` at every intermediate step, so a path naming a member the served entity does not have resolves
     * to null on every render — silently, since a non-required consumer does not throw. Asserting the member
     * exists here is what turns "curated" into "curated correctly".
     */
    public function testEveryOfferedPathNamesAMemberOfTheServedCategoryEntity(): void
    {
        foreach ($this->provider->provide('category') as $candidate) {
            $segments = explode('.', $candidate->path);

            static::assertCount(
                2,
                $segments,
                \sprintf('Candidate "%s" is not a one-hop path; a deeper path needs a Struct at every step.', $candidate->path)
            );
            static::assertSame('category', $segments[0]);
            static::assertTrue(
                property_exists(SalesChannelCategoryEntity::class, $segments[1]),
                \sprintf('Candidate "%s" names a member the served category entity does not have.', $candidate->path)
            );
        }
    }

    public function testEveryCandidateCarriesDistinctSnippetKeysForItsLabelAndDescription(): void
    {
        $labels = [];

        foreach ($this->provider->provide('category') as $candidate) {
            static::assertStringStartsWith('sw-experience-studio.mapping.category.', $candidate->label);
            static::assertNotSame($candidate->label, $candidate->description);

            $labels[] = $candidate->label;
        }

        static::assertSame($labels, array_unique($labels));
    }

    private function candidateFor(string $path): MappingCandidate
    {
        foreach ($this->provider->provide('category') as $candidate) {
            if ($candidate->path === $path) {
                return $candidate;
            }
        }

        static::fail(\sprintf('The category catalogue does not offer "%s".', $path));
    }
}
