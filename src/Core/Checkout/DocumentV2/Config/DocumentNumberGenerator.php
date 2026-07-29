<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Config;

use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;

/**
 * Generates the shared document number for one logical document.
 *
 * All persisted output formats of the same generation request reuse this number.
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class DocumentNumberGenerator
{
    final public const NUMBER_RANGE_DOCUMENT_TYPE_PREFIX = 'document_';

    public function __construct(
        private NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
    ) {
    }

    public function generate(
        DocumentGenerationRequest $generationRequest,
        OrderEntity $order,
        Context $context,
    ): string {
        $type = self::NUMBER_RANGE_DOCUMENT_TYPE_PREFIX . $generationRequest->documentType;

        return $this->numberRangeValueGenerator->getValue(
            type: $type,
            context: $context,
            salesChannelId: $order->getSalesChannelId(),
        );
    }
}
