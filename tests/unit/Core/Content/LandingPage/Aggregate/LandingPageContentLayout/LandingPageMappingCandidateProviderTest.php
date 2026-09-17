<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\LandingPage\Aggregate\LandingPageContentLayout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageContentLayout\LandingPageContentLayoutDefinition;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageContentLayout\LandingPageMappingCandidateProvider;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Framework\ContentSystem\Helper\ContentLayoutMetadataDeriver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
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
        static::assertNull($candidate->projection);
        static::assertSame('basic', $candidate->group);
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
