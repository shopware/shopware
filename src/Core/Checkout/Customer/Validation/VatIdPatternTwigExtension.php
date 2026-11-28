<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation;

use Shopware\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Lets forms check a VAT ID against the same EU member state patterns the server accepts.
 *
 * @internal
 */
#[Package('checkout')]
class VatIdPatternTwigExtension extends AbstractExtension
{
    public function __construct(private readonly VatIdPatternProvider $vatIdPatternProvider)
    {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('sw_eu_vat_id_patterns', $this->getEuPatterns(...)),
        ];
    }

    /**
     * @return list<string>
     */
    public function getEuPatterns(): array
    {
        return array_values($this->vatIdPatternProvider->getEuPatterns());
    }
}
