<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\ProductAssignment\Api;

use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\Constraint\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 */
#[Package('discovery')]
final readonly class SalesChannelProductAssignmentRequest
{
    public const MAX_PRODUCT_IDS = 1000;

    /**
     * Every product group and category is resolved against the whole catalog,
     * so these lists stay far shorter than the explicit product ids.
     */
    public const MAX_PRODUCT_STREAM_IDS = 25;

    public const MAX_CATEGORY_IDS = 50;

    /**
     * @param list<string> $productIds
     * @param list<string> $productStreamIds
     * @param list<string> $categoryIds
     */
    public function __construct(
        #[Assert\Type('list')]
        #[Assert\Count(max: self::MAX_PRODUCT_IDS)]
        #[Assert\All([new Uuid()])]
        public array $productIds = [],
        #[Assert\Type('list')]
        #[Assert\Count(max: self::MAX_PRODUCT_STREAM_IDS)]
        #[Assert\All([new Uuid()])]
        public array $productStreamIds = [],
        #[Assert\Type('list')]
        #[Assert\Count(max: self::MAX_CATEGORY_IDS)]
        #[Assert\All([new Uuid()])]
        public array $categoryIds = [],
        #[Assert\Choice(choices: [
            ProductVisibilityDefinition::VISIBILITY_LINK,
            ProductVisibilityDefinition::VISIBILITY_SEARCH,
            ProductVisibilityDefinition::VISIBILITY_ALL,
        ])]
        public int $visibility = ProductVisibilityDefinition::VISIBILITY_ALL,
    ) {
    }
}
