<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Garan;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

#[Package('inventory')]
class GaranLabelTwigFilter extends AbstractExtension
{
    /**
     * @internal
     *
     * @param EntityRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly GaranLabelDurationFormatter $durationFormatter,
        private readonly EntityRepository $productRepository,
        private readonly GaranLabelResolver $resolver,
    ) {
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

    private function loadProduct(?string $productId, Context $context): ?ProductEntity
    {
        if ($productId === null) {
            return null;
        }

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('manufacturer');

        $product = $this->productRepository->search($criteria, $context)->getEntities()->first();

        return $product instanceof ProductEntity ? $product : null;
    }
}
