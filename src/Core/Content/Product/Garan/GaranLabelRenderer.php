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
    public function __construct(private readonly Environment $twig)
    {
    }

    public function render(string $duration, string $brand, string $modelIdentifier): string
    {
        return $this->twig->render('@Framework/garan/label.svg.twig', [
            'guarantee' => $duration,
            'manufacturer' => $brand,
            'productNumber' => $modelIdentifier,
        ]);
    }

    public function renderNestedLabel(string $duration): string
    {
        return $this->twig->render('@Framework/garan/nested-label.svg.twig', [
            'guarantee' => $duration,
        ]);
    }
}
