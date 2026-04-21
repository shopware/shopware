<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Config;

use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationContext;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;

/**
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

    public function generate(DocumentGenerationContext $generationContext, OrderEntity $order): string
    {
        $type = self::NUMBER_RANGE_DOCUMENT_TYPE_PREFIX . $generationContext->getDocumentType();

        return $this->numberRangeValueGenerator->getValue(
            $type,
            $generationContext->getContext(),
            $order->getSalesChannelId(),
        );
    }
}
