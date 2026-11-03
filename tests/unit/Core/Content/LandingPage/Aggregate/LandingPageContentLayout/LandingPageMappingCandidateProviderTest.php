<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\LandingPage\Aggregate\LandingPageContentLayout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageContentLayout\LandingPageContentLayoutDefinition;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageContentLayout\LandingPageMappingCandidateProvider;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
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
#[CoversClass(LandingPageMappingCandidateProvider::class)]
class LandingPageMappingCandidateProviderTest extends TestCase
{
    private LandingPageMappingCandidateProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new LandingPageMappingCandidateProvider(
            new LandingPageContentLayoutDefinition(new ContentLayoutMetadataDeriver())
        );
    }

    #[DataProvider('textFieldProvider')]
    public function testResolvesEachTextFieldAndItsTranslationFallback(string $member, bool $translated, string $expected): void
    {
        $entity = new LandingPageEntity();
        $entity->assign([
            'name' => 'Raw name',
            'url' => 'Raw url',
            'metaTitle' => 'Raw metaTitle',
            'metaDescription' => 'Raw metaDescription',
            'keywords' => 'Raw keywords',
        ]);
        $entity->setTranslated($translated ? [
            'name' => 'Translated name',
            'url' => 'Translated url',
            'metaTitle' => 'Translated metaTitle',
            'metaDescription' => 'Translated metaDescription',
            'keywords' => 'Translated keywords',
        ] : []);
        $context = new MappingSourceResolutionContext(new StoredElement('element-1', 'Sw:Text'), ['landing_page' => $entity], [], new RenderingCacheContext());

        static::assertSame($expected, $this->provider->resolveRootSource(MappingSourceReference::root('landing_page', $member), $context)->value);
    }

    /**
     * @return iterable<string, array{string, bool, string}>
     */
    public static function textFieldProvider(): iterable
    {
        yield 'name with translations' => ['name', true, 'Translated name'];
        yield 'name raw fallback' => ['name', false, 'Raw name'];
        yield 'url with translations' => ['url', true, 'Raw url'];
        yield 'url raw fallback' => ['url', false, 'Raw url'];
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
            LandingPageMappingCandidateProvider::registeredCandidateKeys()['landing_page'],
            array_column($provider->provide('landing_page'), 'path'),
        );
    }

    public function testClaimsTheLandingPageRootSourceOnly(): void
    {
        static::assertTrue($this->provider->supports('landing_page'));
        static::assertFalse($this->provider->supports('category'));
        static::assertFalse($this->provider->supports('none'));
    }

    public function testOffersTheLandingPageNameAsAStringCandidate(): void
    {
        $candidate = $this->candidateFor('landing_page.name');

        static::assertSame('string', $candidate->valueType);
        static::assertSame(ContextType::Single, $candidate->contextType);
        static::assertSame('basic', $candidate->group);
    }

    public function testResolvesTheActiveLandingPageTranslation(): void
    {
        $landingPage = new LandingPageEntity();
        $landingPage->setName('Raw name');
        $landingPage->setTranslated(['name' => 'Storefront name']);
        $context = new MappingSourceResolutionContext(new StoredElement('element-1', 'Sw:Text'), ['landing_page' => $landingPage], [], new RenderingCacheContext());

        static::assertSame('Storefront name', $this->provider->resolveRootSource(MappingSourceReference::root('landing_page', 'name'), $context)->value);
    }

    public function testEveryOfferedPathNamesADirectMemberOfTheLandingPageEntity(): void
    {
        foreach ($this->provider->provide('landing_page') as $candidate) {
            $segments = explode('.', $candidate->path);

            static::assertCount(2, $segments);
            static::assertSame('landing_page', $segments[0]);
            static::assertTrue(
                property_exists(LandingPageEntity::class, $segments[1]),
                \sprintf('Candidate "%s" names no landing-page member.', $candidate->path)
            );
        }
    }

    public function testEveryCandidateCarriesDistinctSnippetKeys(): void
    {
        $labels = [];

        foreach ($this->provider->provide('landing_page') as $candidate) {
            static::assertStringStartsWith('sw-experience-studio.mapping.landingPage.', $candidate->label);
            static::assertNotSame($candidate->label, $candidate->description);
            $labels[] = $candidate->label;
        }

        static::assertSame($labels, array_unique($labels));
    }

    private function candidateFor(string $path): MappingCandidate
    {
        foreach ($this->provider->provide('landing_page') as $candidate) {
            if ($candidate->path === $path) {
                return $candidate;
            }
        }

        static::fail(\sprintf('The landing-page catalogue does not offer "%s".', $path));
    }
}
