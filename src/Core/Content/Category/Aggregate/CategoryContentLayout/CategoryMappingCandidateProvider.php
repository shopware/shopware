<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Aggregate\CategoryContentLayout;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\Provider\AbstractMappingCandidateProvider;
use Shopware\Core\Framework\Log\Package;

/**
 * The mapping catalogue for a listing page layout: the category fields an author may bind a mappable element
 * property to.
 *
 * Every path here reads a member that sits DIRECTLY on the category entity, which is what makes it resolvable:
 * `ContextPathResolver` needs a `Struct` at every intermediate step, so a one-hop path has no intermediate to
 * fail on. `media` is offered because `CategoryContentLayoutDefinition::getEntityAssociations()` eager-loads
 * it — an association the page-level requirement does not load would resolve to null on every render.
 *
 * The translated members (`name`, `description`, the meta fields) need no `.translated` hop: the DAL has
 * already written the context language's value onto the member itself by the time the layout renders, and
 * `category.translated.name` could not resolve anyway, because `translated` is a plain array.
 *
 * @internal
 */
#[Package('content')]
class CategoryMappingCandidateProvider extends AbstractMappingCandidateProvider
{
    private const SNIPPET_ROOT = 'sw-experience-studio.mapping.category';

    public function __construct(private readonly CategoryContentLayoutDefinition $definition)
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
            $this->text('description', 'basic'),
            $this->text('metaTitle', 'seo'),
            $this->text('metaDescription', 'seo'),
            $this->text('keywords', 'seo'),
            new MappingCandidate(
                path: $this->path('media'),
                label: self::SNIPPET_ROOT . '.media.label',
                description: self::SNIPPET_ROOT . '.media.description',
                group: 'media',
                valueType: MediaEntity::class,
            ),
        ];
    }

    private function text(string $member, string $group): MappingCandidate
    {
        return new MappingCandidate(
            path: $this->path($member),
            label: self::SNIPPET_ROOT . '.' . $member . '.label',
            description: self::SNIPPET_ROOT . '.' . $member . '.description',
            group: $group,
            valueType: 'string',
        );
    }

    /**
     * The leading segment names the page-level data requirement the value resolves against, which for an
     * entity-assignable layout is the entity type itself — see
     * `AbstractContentLayoutAssignableDefinition::getPageDataRequirements()`.
     */
    private function path(string $member): string
    {
        return $this->definition->getContentLayoutEntityType() . '.' . $member;
    }
}
