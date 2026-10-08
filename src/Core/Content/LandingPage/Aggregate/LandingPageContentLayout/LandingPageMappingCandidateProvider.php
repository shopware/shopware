<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage\Aggregate\LandingPageContentLayout;

use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\Provider\AbstractMappingCandidateProvider;
use Shopware\Core\Framework\Log\Package;

/**
 * The mapping catalogue for a landing-page layout.
 *
 * The translated members are available directly on the entity in the request language. Every candidate is a
 * one-hop path, so resolving it needs no traversal through the entity's array-backed translation or custom-field
 * members.
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
