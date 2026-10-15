<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductContentLayout;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolution;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
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
 * The provider reads the declared fields through storefront-facing accessors. Translated members prefer the
 * active translation, which can carry an inherited product value even when the raw member is null.
 *
 * The two media offers unwrap product-media assignment records here, so their declared value types match
 * what the provider returns. Both depend on
 * `ProductContentLayoutDefinition::getEntityAssociations()` eager-loading `media.media`
 * and `cover.media`; an unloaded association resolves to null and omits the mapped destination.
 *
 * @internal
 */
#[Package('discovery')]
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
            new MappingCandidate(
                path: $rootSource,
                label: self::SNIPPET_ROOT . '.entity.label',
                description: self::SNIPPET_ROOT . '.entity.description',
                group: 'basic',
                valueType: SalesChannelProductEntity::class,
                source: MappingSourceReference::root($rootSource),
            ),
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
            ),
            new MappingCandidate(
                path: $this->path('media'),
                label: self::SNIPPET_ROOT . '.media.label',
                description: self::SNIPPET_ROOT . '.media.description',
                group: 'media',
                valueType: MediaCollection::class,
                contextType: ContextType::Collection,
            ),
        ];
    }

    public function resolveRootSource(MappingSourceReference $source, MappingSourceResolutionContext $context): MappingSourceResolution
    {
        if ($source->type !== 'root' || !$this->supports($source->id)) {
            return MappingSourceResolution::noValue();
        }

        $product = $context->rootValues[$source->id] ?? null;
        if (!$product instanceof SalesChannelProductEntity) {
            return MappingSourceResolution::noValue();
        }

        return match ($source->path) {
            null => MappingSourceResolution::resolved($product),
            'name' => MappingSourceResolution::resolved($product->getTranslation('name') ?? $product->getName()),
            'description' => MappingSourceResolution::resolved($product->getTranslation('description') ?? $product->getDescription()),
            'productNumber' => MappingSourceResolution::resolved($product->getProductNumber()),
            'metaTitle' => MappingSourceResolution::resolved($product->getTranslation('metaTitle') ?? $product->getMetaTitle()),
            'metaDescription' => MappingSourceResolution::resolved($product->getTranslation('metaDescription') ?? $product->getMetaDescription()),
            'keywords' => MappingSourceResolution::resolved($product->getTranslation('keywords') ?? $product->getKeywords()),
            'cover' => MappingSourceResolution::resolved($product->getCover()?->getMedia()),
            'media' => MappingSourceResolution::resolved($this->media($product->getMedia())),
            default => MappingSourceResolution::noValue(),
        };
    }

    private function media(?ProductMediaCollection $assignments): ?MediaCollection
    {
        if ($assignments === null) {
            return null;
        }

        $sorted = array_values($assignments->getElements());
        usort($sorted, static fn (ProductMediaEntity $a, ProductMediaEntity $b): int => $a->getPosition() <=> $b->getPosition());

        $media = new MediaCollection();
        foreach ($sorted as $assignment) {
            $entity = $assignment->getMedia();
            if ($entity !== null) {
                $media->add($entity);
            }
        }

        return $media;
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
