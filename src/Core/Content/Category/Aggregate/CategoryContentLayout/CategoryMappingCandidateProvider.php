<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Aggregate\CategoryContentLayout;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolution;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
use Shopware\Core\Framework\ContentSystem\Mapping\Provider\AbstractMappingCandidateProvider;
use Shopware\Core\Framework\Log\Package;

/**
 * The mapping catalogue for a listing page layout: the category fields an author may bind a mappable element
 * property to.
 *
 * Reads only the declared members through entity accessors, preferring the active translation where relevant.
 * `media` is offered because `CategoryContentLayoutDefinition::getEntityAssociations()` eager-loads it.
 *
 * @internal
 */
#[Package('discovery')]
class CategoryMappingCandidateProvider extends AbstractMappingCandidateProvider
{
    private const SNIPPET_ROOT = 'sw-experience-studio.mapping.category';

    public function __construct(private readonly CategoryContentLayoutDefinition $definition)
    {
    }

    public static function registeredCandidateKeys(): array
    {
        return ['category' => [
            'category.name',
            'category.description',
            'category.metaTitle',
            'category.metaDescription',
            'category.keywords',
            'category.media',
        ]];
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

    public function resolveRootSource(MappingSourceReference $source, MappingSourceResolutionContext $context): MappingSourceResolution
    {
        if ($source->type !== 'root' || !$this->supports($source->id)) {
            return MappingSourceResolution::noValue();
        }

        $category = $context->rootValues[$source->id] ?? null;
        if (!$category instanceof CategoryEntity) {
            return MappingSourceResolution::noValue();
        }

        $value = match ($source->path) {
            'name' => $category->getTranslation('name') ?? $category->getName(),
            'description' => $category->getTranslation('description') ?? $category->getDescription(),
            'metaTitle' => $category->getTranslation('metaTitle') ?? $category->getMetaTitle(),
            'metaDescription' => $category->getTranslation('metaDescription') ?? $category->getMetaDescription(),
            'keywords' => $category->getTranslation('keywords') ?? $category->getKeywords(),
            'media' => $category->getMedia(),
            default => null,
        };

        if (!\in_array($source->path, ['name', 'description', 'metaTitle', 'metaDescription', 'keywords', 'media'], true)) {
            return MappingSourceResolution::noValue();
        }

        if ($source->path === 'media' && $value === null) {
            return MappingSourceResolution::noValue();
        }

        return MappingSourceResolution::resolved($value);
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
