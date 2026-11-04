<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Content\Product\Exception\ProductLineItemDifferentIdException;
use Shopware\Core\Content\Product\Exception\ProductLineItemInconsistentException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\SetNullOnDeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\OutcomeRow;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Integration\Core\Content\Product\Cart\ProductLineItemCommandValidatorTest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see ProductLineItemCommandValidatorTest
 */
#[Package('inventory')]
class ProductLineItemCommandValidator implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly WriteOutcome $writeOutcome)
    {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $commands = array_values(array_filter(
            $event->getCommandsForEntity(OrderLineItemDefinition::ENTITY_NAME),
            static fn (WriteCommand $command): bool => !$command instanceof SetNullOnDeleteCommand
        ));

        foreach ($this->writeOutcome->resultingRows($commands, ['type', 'product_id', 'referenced_id', 'payload']) as $lineItem) {
            if ($lineItem->getResultingValue('type') !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }

            $lineItemId = Uuid::fromBytesToHex($lineItem->primaryKey['id']);

            $productIdChanged = $lineItem->payloadContainsAny('product_id');
            $referenceIdChanged = $lineItem->payloadContainsAny('referenced_id');
            $orderNumberChanged = \array_key_exists('productNumber', $this->lineItemPayload($lineItem));

            if (!$productIdChanged && !$referenceIdChanged && !$orderNumberChanged) {
                continue;
            }

            $productId = $lineItem->getPayloadValue('product_id');
            if ((\is_string($productId) ? Uuid::fromBytesToHex($productId) : null) !== $lineItem->getPayloadValue('referenced_id')) {
                $event->getExceptions()->add(
                    new ProductLineItemDifferentIdException($lineItemId)
                );
            }

            if ($productIdChanged && $referenceIdChanged && $orderNumberChanged) {
                continue;
            }

            $event->getExceptions()->add(
                new ProductLineItemInconsistentException($lineItemId)
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function lineItemPayload(OutcomeRow $lineItem): array
    {
        $payload = $lineItem->getPayloadValue('payload');

        return \is_string($payload) ? json_decode($payload, true, 512, \JSON_THROW_ON_ERROR) : [];
    }
}
