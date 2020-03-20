<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax\Struct;

use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class TaxRule extends Struct
{
    /**
     * @var float
     */
    protected $taxRate;

    /**
     * @var float
     */
    protected $percentage;

    public function __construct(float $taxRate, float $percentage = 100.0)
    {
        $this->taxRate = $taxRate;
        $this->percentage = $percentage;
    }

    public function getTaxRate(): float
    {
        return $this->taxRate;
    }

    public function getPercentage(): float
    {
        return $this->percentage;
    }

    public static function getConstraints(): array
    {
        return [
            'taxRate' => [new NotBlank(), new Type('numeric')],
            'percentage' => [new Type('numeric')],
        ];
    }

    public function getApiAlias(): string
    {
        return 'cart_tax_rule';
    }
}
