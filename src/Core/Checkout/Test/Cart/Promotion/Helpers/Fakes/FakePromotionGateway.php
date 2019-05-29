<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Fakes;

use Shopware\Core\Checkout\Promotion\Gateway\PromotionGatewayInterface;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class FakePromotionGateway implements PromotionGatewayInterface
{
    /**
     * @var PromotionEntity[]
     */
    private $promotions = [];

    public function __construct(array $promotions)
    {
        $this->promotions = $promotions;
    }

    public function get(Criteria $criteria, SalesChannelContext $context): EntityCollection
    {
        return new EntityCollection($this->promotions);
    }
}
