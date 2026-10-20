<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Garan;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
class GaranLabelResolver
{
    public const LABEL_TYPE_FULL = 'full';
    public const LABEL_TYPE_NESTED = 'nested';

    public function __construct(
        private readonly GaranLabelDurationFormatter $durationFormatter,
        private readonly GaranLabelRenderer $renderer,
    ) {
    }

    public function resolve(ProductEntity $product, string $type = self::LABEL_TYPE_FULL): ?string
    {
        if (!$product->isGuaranteeConfirmed()) {
            return null;
        }

        $brand = trim((string) $product->getManufacturer()?->getTranslation('name'));
        $modelIdentifier = trim((string) $product->getManufacturerNumber());
        $duration = $this->durationFormatter->formatMonths($product->getGuaranteeMonths());

        if ($duration === null || $brand === '' || $modelIdentifier === '') {
            return null;
        }

        if ($type === self::LABEL_TYPE_NESTED) {
            return $this->renderer->renderNestedLabel($duration);
        }

        return $this->renderer->render($duration, $brand, $modelIdentifier);
    }
}
