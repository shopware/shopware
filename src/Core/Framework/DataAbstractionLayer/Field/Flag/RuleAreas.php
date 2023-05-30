<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class RuleAreas extends Flag
{
    final public const PRODUCT_AREA = 'product';
    final public const PAYMENT_AREA = 'payment';
    final public const SHIPPING_AREA = 'shipping';
    final public const PROMOTION_AREA = 'promotion';
    final public const CATEGORY_AREA = 'category';
    final public const LANDING_PAGE_AREA = 'landing-page';
    final public const FLOW_AREA = 'flow';
    final public const FLOW_CONDITION_AREA = 'flow-condition';

    /**
     * @var string[]
     */
    private readonly array $areas;

    public function __construct(string ...$areas)
    {
        $this->areas = $areas;
    }

    public function parse(): \Generator
    {
        yield 'rule_areas' => true;
    }

    /**
     * @return string[]
     */
    public function getAreas(): array
    {
        return $this->areas;
    }
}
