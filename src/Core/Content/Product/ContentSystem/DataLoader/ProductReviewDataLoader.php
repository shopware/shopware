<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ContentSystem\DataLoader;

use Shopware\Core\Content\Product\SalesChannel\Review\AbstractProductReviewLoader;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

use function Symfony\Component\String\u;

/**
 * Produces the full {@see ProductReviewResult} a review element renders: the review collection plus its rating
 * matrix, per-language totals and the current customer's own review. It resolves the product id from the
 * element property named by its `property` config (default `productId`, typically the `{{productId}}`
 * placeholder on a product-rooted layout) and delegates the actual load to {@see AbstractProductReviewLoader},
 * the one place that shape is assembled — so the content-system path and the storefront widget cannot drift.
 *
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<ProductReviewResult>
 */
#[Package('after-sales')]
class ProductReviewDataLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'product_review';

    public function __construct(
        private readonly AbstractProductReviewLoader $productReviewLoader
    ) {
    }

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: 'productId'),
        ]);
    }

    public function load(
        LoaderInputs $inputs,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $productId = $inputs->stringOrNull('property');

        if ($productId === null) {
            return ContentDataLoaderResult::notFound();
        }

        $productId = u($productId)->lower()->toString();

        // An unsubstituted placeholder such as "{{productId}}" passes LoaderInputResolver::dereference()
        // untouched; guard after the lowercase (Uuid::VALID_PATTERN is lowercase-only) before it reaches the
        // review loader's `product.id` equals filter.
        if (!Uuid::isValid($productId)) {
            return ContentDataLoaderResult::notFound();
        }

        // Any ShopwareHttpException (e.g. reviews switched off for the sales channel) degrades the element to
        // notFound(); anything else propagates, matching the degradation boundary in
        // Framework/ContentSystem/Hydration/DataLoader/README.md.
        try {
            $result = $this->productReviewLoader->load($request, $context, $productId);
        } catch (ShopwareHttpException) {
            return ContentDataLoaderResult::notFound();
        }

        return ContentDataLoaderResult::cachedExternally($result);
    }
}
