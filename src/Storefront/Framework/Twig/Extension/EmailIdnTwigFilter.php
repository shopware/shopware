<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Extension;

use Shopware\Core\Checkout\Customer\Service\EmailIdnConverter;
use Shopware\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

#[Package('storefront')]
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
