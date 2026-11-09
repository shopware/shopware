<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Extension;

use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<NoContentResponse>
 */
#[Package('after-sales')]
final class ProductReviewSaveRouteExtension extends Extension
{
    public const NAME = 'product-review-save-route.save';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $productId,
        public readonly RequestDataBag $data,
        public readonly SalesChannelContext $context,
    ) {
    }
}
