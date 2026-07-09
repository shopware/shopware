<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Garan;

use Shopware\Core\Framework\Log\Package;
use Twig\Environment;

/**
 * @internal
 */
#[Package('inventory')]
class GaranLabelRenderer
{
    private const TEMPLATE = '@Framework/garan/label.svg.twig';

    public function __construct(private readonly Environment $twig)
    {
    }

    public function render(string $duration, string $brand, string $modelIdentifier): string
    {
        return $this->twig->render(self::TEMPLATE, [
            'guarantee' => $duration,
            'manufacturer' => $brand,
            'productNumber' => $modelIdentifier,
        ]);
    }
}
