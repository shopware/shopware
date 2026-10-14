<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Garan;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

#[Package('inventory')]
class GaranLabelTwigFilter extends AbstractExtension
{
    /**
     * @var \WeakMap<Context, array<string, ProductEntity|null>>
     */
    private \WeakMap $loadedProducts;

    /**
     * @var \WeakMap<Context, list<string>>
     */
    private \WeakMap $pendingProductIds;

    /**
     * @internal
     *
     * @param EntityRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly GaranLabelDurationFormatter $durationFormatter,
        private readonly EntityRepository $productRepository,
        private readonly GaranLabelResolver $resolver,
        private readonly GaranLabelInlineImage $inlineImage,
    ) {
        $this->loadedProducts = new \WeakMap();
        $this->pendingProductIds = new \WeakMap();
    }

    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('sw_garan_label_duration', $this->formatDuration(...)),
            new TwigFilter('sw_garan_label', $this->render(...), ['is_safe' => ['html']]),
            new TwigFilter('sw_garan_label_nested', $this->renderNestedLabel(...), ['is_safe' => ['html']]),
            new TwigFilter('sw_garan_label_data_uri', $this->renderAsDataUri(...)),
            new TwigFilter('sw_garan_label_nested_uri', $this->renderNestedAsDataUri(...)),
            new TwigFilter('sw_garan_label_text_length', $this->fitTextLength(...)),
            new TwigFilter('sw_garan_label_duration_text_length', $this->fitDurationTextLength(...)),
            // @deprecated tag:v6.8.0 - remove together with `resolveMailLabel()`
            new TwigFilter('sw_garan_label_mail', $this->resolveMailLabel(...)),
        ];
    }

    public function formatDuration(?int $guaranteeMonths): ?string
    {
        return $this->durationFormatter->formatMonths($guaranteeMonths);
    }

    /**
     * The label templates are also included directly, so the fit has to be available to the
     * templates themselves rather than only to `GaranLabelRenderer`.
     */
    public function fitTextLength(?string $value, float $clearWidth, float $fontSize, float $letterSpacing = 0.0): ?float
    {
        return GaranLabelTextFitter::fitTextLength($value, $clearWidth, $fontSize, $letterSpacing);
    }

    public function fitDurationTextLength(?string $value, float $clearWidth, float $fontSize, float $letterSpacing = 0.0): ?float
    {
        return GaranLabelTextFitter::fitDurationTextLength($value, $clearWidth, $fontSize, $letterSpacing);
    }

    public function render(?string $productId, Context $context): ?string
    {
        $product = $this->loadProduct($productId, $context);

        if ($product === null) {
            return null;
        }

        return $this->resolver->resolve($product, GaranLabelResolver::LABEL_TYPE_FULL);
    }

    public function renderNestedLabel(?string $productId, Context $context): ?string
    {
        $product = $this->loadProduct($productId, $context);

        if ($product === null) {
            return null;
        }

        return $this->resolver->resolve($product, GaranLabelResolver::LABEL_TYPE_NESTED);
    }

    /**
     * Email clients strip or garble inline <svg> markup, so mail templates need the label
     * embedded as an <img> data URI instead of raw SVG.
     */
    public function renderAsDataUri(?string $productId, Context $context): ?string
    {
        $svg = $this->render($productId, $context);

        if ($svg === null) {
            return null;
        }

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public function renderNestedAsDataUri(?string $productId, Context $context): ?string
    {
        $svg = $this->renderNestedLabel($productId, $context);

        if ($svg === null) {
            return null;
        }

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, mail templates read the label from the `garanLabels` template data instead
     *
     * @return array{cid: string|null, duration: string}|null
     */
    public function resolveMailLabel(?string $productId, Context $context): ?array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'the `garanLabels` mail template data'),
        );

        $product = $this->loadProduct($productId, $context);

        if ($product === null) {
            return null;
        }

        $duration = $this->resolver->resolveDuration($product);

        if ($duration === null) {
            return null;
        }

        $name = $this->inlineImage->getName((int) $product->getGuaranteeMonths());

        return [
            'cid' => $name !== null ? 'cid:' . $name : null,
            'duration' => $duration,
        ];
    }

    /**
     * @internal
     *
     * @param list<string> $productIds
     */
    public function preloadProducts(array $productIds, Context $context): void
    {
        $this->pendingProductIds[$context] = [...$this->pendingProductIds[$context] ?? [], ...$productIds];
    }

    private function loadProduct(?string $productId, Context $context): ?ProductEntity
    {
        if ($productId === null) {
            return null;
        }

        $loaded = $this->loadedProducts[$context] ?? [];

        if (\array_key_exists($productId, $loaded)) {
            return $loaded[$productId];
        }

        $productIds = array_values(array_unique(array_diff([...$this->pendingProductIds[$context] ?? [], $productId], array_keys($loaded))));
        unset($this->pendingProductIds[$context]);

        $criteria = new Criteria($productIds);
        $criteria->addAssociation('manufacturer');

        $products = $this->productRepository->search($criteria, $context)->getEntities();

        foreach ($productIds as $id) {
            $loaded[$id] = $products->get($id);
        }

        $this->loadedProducts[$context] = $loaded;

        return $loaded[$productId];
    }
}
