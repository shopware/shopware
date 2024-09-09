<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Filter;

use Shopware\Core\Checkout\Customer\Service\EmailIdnConverter;
use Shopware\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * @internal
 */
#[Package('checkout')]
class EmailIdnTwigFilter extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('decodeIdnEmail', [EmailIdnConverter::class, 'decode']),
            new TwigFilter('encodeIdnEmail', [EmailIdnConverter::class, 'encode']),
        ];
    }
}
