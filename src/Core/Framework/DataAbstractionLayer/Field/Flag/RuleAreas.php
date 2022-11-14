<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

/**
 * @package core
 */
class RuleAreas extends Flag
{
    public const PRODUCT_AREA = 'product';
    public const PAYMENT_AREA = 'payment';
    public const SHIPPING_AREA = 'shipping';
    public const PROMOTION_AREA = 'promotion';
    public const CATEGORY_AREA = 'category';
    public const LANDING_PAGE_AREA = 'landing-page';
    public const FLOW_AREA = 'flow';
    public const FLOW_CONDITION_AREA = 'flow-condition';

    /**
     * @var string[]
     */
    private array $areas;

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
