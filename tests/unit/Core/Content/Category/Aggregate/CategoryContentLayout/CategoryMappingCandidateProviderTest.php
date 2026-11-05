<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\Aggregate\CategoryContentLayout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryContentLayout\CategoryContentLayoutDefinition;
use Shopware\Core\Content\Category\Aggregate\CategoryContentLayout\CategoryMappingCandidateProvider;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\SalesChannel\SalesChannelCategoryEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\ContentSystem\Cache\MappedMediaCacheInvalidator;
use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\Helper\ContentLayoutMetadataDeriver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
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

    #[DataProvider('textFieldProvider')]
    public function testResolvesEachTextFieldAndItsTranslationFallback(string $member, bool $translated, string $expected): void
    {
        $entity = new CategoryEntity();
        $entity->assign([
            'name' => 'Raw name',
            'description' => 'Raw description',
            'metaTitle' => 'Raw metaTitle',
            'metaDescription' => 'Raw metaDescription',
            'keywords' => 'Raw keywords',
        ]);
        $entity->setTranslated($translated ? [
            'name' => 'Translated name',
            'description' => 'Translated description',
            'metaTitle' => 'Translated metaTitle',
            'metaDescription' => 'Translated metaDescription',
            'keywords' => 'Translated keywords',
        ] : []);
        $context = new MappingSourceResolutionContext(new StoredElement('element-1', 'Sw:Text'), ['category' => $entity], [], new RenderingCacheContext());

        static::assertSame($expected, $this->provider->resolveRootSource(MappingSourceReference::root('category', $member), $context)->value);
    }

    /**
     * @return iterable<string, array{string, bool, string}>
     */
    public static function textFieldProvider(): iterable
    {
        yield 'name with translations' => ['name', true, 'Translated name'];
        yield 'name raw fallback' => ['name', false, 'Raw name'];
        yield 'description with translations' => ['description', true, 'Translated description'];
        yield 'description raw fallback' => ['description', false, 'Raw description'];
        yield 'metaTitle with translations' => ['metaTitle', true, 'Translated metaTitle'];
        yield 'metaTitle raw fallback' => ['metaTitle', false, 'Raw metaTitle'];
        yield 'metaDescription with translations' => ['metaDescription', true, 'Translated metaDescription'];
        yield 'metaDescription raw fallback' => ['metaDescription', false, 'Raw metaDescription'];
        yield 'keywords with translations' => ['keywords', true, 'Translated keywords'];
        yield 'keywords raw fallback' => ['keywords', false, 'Raw keywords'];
    }

    public function testStableDeclarationsMatchTheOfferedCatalogue(): void
    {
        $provider = $this->provider;

        static::assertSame(
            CategoryMappingCandidateProvider::registeredCandidateKeys()['category'],
            array_column($provider->provide('category'), 'path'),
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
        static::assertSame('basic', $candidate->group);
    }

    public function testOffersTheCategoryImageAsAMediaEntityCandidate(): void
    {
        $candidate = $this->candidateFor('category.media');

        static::assertSame(MediaEntity::class, $candidate->valueType);
        static::assertSame('media', $candidate->group);
    }

    public function testResolvesTheActiveCategoryTranslation(): void
    {
        $category = new CategoryEntity();
        $category->setName('Raw name');
        $category->setTranslated(['name' => 'Storefront name']);
        $context = new MappingSourceResolutionContext(new StoredElement('element-1', 'Sw:Text'), ['category' => $category], [], new RenderingCacheContext());

        static::assertSame('Storefront name', $this->provider->resolveRootSource(MappingSourceReference::root('category', 'name'), $context)->value);
    }

    public function testOnlyReadingTheCategoryMediaAddsItsDependency(): void
    {
        $media = new MediaEntity();
        $media->setId('mapped-media');
        $category = new CategoryEntity();
        $category->setName('Category');
        $category->setMedia($media);
        $cache = new RenderingCacheContext();
        $cache->addTags(['existing-dependency']);
        $cache->disable();
        $context = new MappingSourceResolutionContext(new StoredElement('element', 'Sw:Media:Image'), ['category' => $category], [], $cache);

        $this->provider->resolveRootSource(MappingSourceReference::root('category', 'name'), $context);
        static::assertSame(['existing-dependency'], $cache->getTags());
        static::assertSame($media, $this->provider->resolveRootSource(MappingSourceReference::root('category', 'media'), $context)->value);
        static::assertSame(['existing-dependency', MappedMediaCacheInvalidator::buildName('mapped-media')], $cache->getTags());
        static::assertTrue($cache->isDisabled());
    }

    public function testMissingCategoryMediaAddsNoMediaDependency(): void
    {
        $category = new CategoryEntity();
        $cache = new RenderingCacheContext();
        $context = new MappingSourceResolutionContext(new StoredElement('element', 'Sw:Media:Image'), ['category' => $category], [], $cache);

        static::assertTrue($this->provider->resolveRootSource(MappingSourceReference::root('category', 'media'), $context)->isNoValue());
        static::assertSame([], $cache->getTags());
    }

    /**
     * Every offered path names a storefront category member. The provider explicitly reads those members so
     * an accidentally renamed field would otherwise resolve to null on every render.
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
