<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Fakes;

use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Checkout\Promotion\PromotionGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class FakePromotionGateway implements PromotionGatewayInterface
{
    /**
     * @var PromotionEntity[]
     */
    private $contextPromotions = [];

    /**
     * @var PromotionEntity[]
     */
    private $codePromotions = [];

    public function __construct(array $contextPromotions, array $codePromotions)
    {
        $this->contextPromotions = $contextPromotions;
        $this->codePromotions = $codePromotions;
    }

    public function getAutomaticPromotions(SalesChannelContext $context): EntityCollection
    {
        return new EntityCollection($this->contextPromotions);
    }

    public function getByCodes(array $codes, SalesChannelContext $context): EntityCollection
    {
        return new EntityCollection($this->codePromotions);
    }
}
