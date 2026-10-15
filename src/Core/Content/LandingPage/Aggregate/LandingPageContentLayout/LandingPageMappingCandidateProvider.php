<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage\Aggregate\LandingPageContentLayout;

use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolution;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
use Shopware\Core\Framework\ContentSystem\Mapping\Provider\AbstractMappingCandidateProvider;
use Shopware\Core\Framework\Log\Package;

/**
 * The mapping catalogue for a landing-page layout.
 *
 * Reads only the declared members through entity accessors, preferring the active translation where relevant.
 *
 * @internal
 */
#[Package('discovery')]
class LandingPageMappingCandidateProvider extends AbstractMappingCandidateProvider
{
    private const SNIPPET_ROOT = 'sw-experience-studio.mapping.landingPage';

    public function __construct(private readonly LandingPageContentLayoutDefinition $definition)
    {
    }

    public function supports(string $rootSource): bool
    {
        return $this->definition->getContentLayoutEntityType() === $rootSource;
    }

    public function provide(string $rootSource): array
    {
        return [
            $this->text('name', 'basic'),
            $this->text('url', 'basic'),
            $this->text('metaTitle', 'seo'),
            $this->text('metaDescription', 'seo'),
            $this->text('keywords', 'seo'),
        ];
    }

    public function resolveRootSource(MappingSourceReference $source, MappingSourceResolutionContext $context): MappingSourceResolution
    {
        if ($source->type !== 'root' || !$this->supports($source->id)) {
            return MappingSourceResolution::noValue();
        }

        $landingPage = $context->rootValues[$source->id] ?? null;
        if (!$landingPage instanceof LandingPageEntity) {
            return MappingSourceResolution::noValue();
        }

        $value = match ($source->path) {
            'name' => $landingPage->getTranslation('name') ?? $landingPage->getName(),
            'url' => $landingPage->getUrl(),
            'metaTitle' => $landingPage->getTranslation('metaTitle') ?? $landingPage->getMetaTitle(),
            'metaDescription' => $landingPage->getTranslation('metaDescription') ?? $landingPage->getMetaDescription(),
            'keywords' => $landingPage->getTranslation('keywords') ?? $landingPage->getKeywords(),
            default => null,
        };

        return \in_array($source->path, ['name', 'url', 'metaTitle', 'metaDescription', 'keywords'], true)
            ? MappingSourceResolution::resolved($value)
            : MappingSourceResolution::noValue();
    }

    private function text(string $member, string $group): MappingCandidate
    {
        return new MappingCandidate(
            path: $this->definition->getContentLayoutEntityType() . '.' . $member,
            label: self::SNIPPET_ROOT . '.' . $member . '.label',
            description: self::SNIPPET_ROOT . '.' . $member . '.description',
            group: $group,
            valueType: 'string',
        );
    }
}
