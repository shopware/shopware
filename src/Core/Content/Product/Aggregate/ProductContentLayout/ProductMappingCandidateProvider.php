<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductContentLayout;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\ContentSystem\Mapping\ProductMediaCollectionToMediaCollectionProjection;
use Shopware\Core\Content\Product\ContentSystem\Mapping\ProductMediaToMediaProjection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\Provider\AbstractMappingCandidateProvider;
use Shopware\Core\Framework\Log\Package;

/**
 * The mapping catalogue for a product page layout: the product fields an author may bind a mappable element
 * property to.
 *
 * The root this resolves against is a {@see SalesChannelProductEntity}, not a `ProductEntity`, because
 * `EntityLoader` loads through the sales-channel repository wherever one is registered. It matters only for
 * anything naming the root's own class; the members below are inherited unchanged.
 *
 * Every path reads a member sitting DIRECTLY on the product entity, which is what makes it resolvable:
 * `ContextPathResolver` needs a `Struct` at every intermediate step, so a one-hop path has no intermediate to
 * fail on. The translated members (`name`, `description`, the meta fields) need no `.translated` hop — the DAL
 * has already written the context language's value onto the member by the time the layout renders, and
 * `product.translated.name` could not resolve anyway, because `translated` is a plain array.
 *
 * The two media offers are the reason projections exist. Neither `cover` nor `media` holds a picture: they
 * hold product-media ASSIGNMENT records, one level away from the {@see MediaEntity} every media element
 * declares. `valueType` here is what the property ends up with, and the projection is the hop that gets it
 * there. Both depend on `ProductContentLayoutDefinition::getEntityAssociations()` eager-loading `media.media`
 * and `cover.media`; an association the page-level requirement does not load resolves to null on every
 * render, which a mapping reads as "fall back to the authored value" rather than as the bug it is.
 *
 * @internal
 */
#[Package('inventory')]
class ProductMappingCandidateProvider extends AbstractMappingCandidateProvider
{
    private const SNIPPET_ROOT = 'sw-experience-studio.mapping.product';

    public function __construct(private readonly ProductContentLayoutDefinition $definition)
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
            $this->text('productNumber', 'basic'),
            $this->text('metaTitle', 'seo'),
            $this->text('metaDescription', 'seo'),
            $this->text('keywords', 'seo'),
            new MappingCandidate(
                path: $this->path('cover'),
                label: self::SNIPPET_ROOT . '.cover.label',
                description: self::SNIPPET_ROOT . '.cover.description',
                group: 'media',
                valueType: MediaEntity::class,
                projection: ProductMediaToMediaProjection::NAME,
            ),
            new MappingCandidate(
                path: $this->path('media'),
                label: self::SNIPPET_ROOT . '.media.label',
                description: self::SNIPPET_ROOT . '.media.description',
                group: 'media',
                valueType: MediaCollection::class,
                contextType: ContextType::Collection,
                projection: ProductMediaCollectionToMediaCollectionProjection::NAME,
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
