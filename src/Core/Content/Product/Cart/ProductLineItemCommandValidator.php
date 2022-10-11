<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Content\Product\Exception\ProductLineItemDifferentIdException;
use Shopware\Core\Content\Product\Exception\ProductLineItemInconsistentException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\SetNullOnDeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - EventSubscribers will become internal in v6.5.0
 */
class ProductLineItemCommandValidator implements EventSubscriberInterface
{
    private Connection $connection;

    /**
     * @internal
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents()
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

        $products = $this->findProducts($event->getCommands());

        foreach ($event->getCommands() as $command) {
            if ($command->getDefinition()->getClass() !== OrderLineItemDefinition::class) {
                continue;
            }
            if ($command instanceof SetNullOnDeleteCommand) {
                continue;
            }

            $payload = $command->getPayload();

            $lineItemId = Uuid::fromBytesToHex($command->getPrimaryKey()['id']);

            $productIdChanged = \array_key_exists('product_id', $payload);

            $referenceIdChanged = \array_key_exists('referenced_id', $payload);

            $lineItemPayload = isset($payload['payload']) ? json_decode($payload['payload'], true) : [];
            $orderNumberChanged = \array_key_exists('productNumber', $lineItemPayload);

            if (!$this->isProduct($products, $payload, $lineItemId)) {
                continue;
            }

            $somethingChanged = $productIdChanged || $referenceIdChanged || $orderNumberChanged;

            $allChanged = $productIdChanged && $referenceIdChanged && $orderNumberChanged;

            // has a field changed?
            if (!$somethingChanged) {
                continue;
            }

            $productId = isset($payload['product_id']) ? Uuid::fromBytesToHex($payload['product_id']) : null;
            $referenceId = $payload['referenced_id'] ?? null;

            if ($productId !== $referenceId) {
                $event->getExceptions()->add(
                    new ProductLineItemDifferentIdException($lineItemId)
                );
            }

            // all fields updated? everything is consistent
            if ($allChanged) {
                continue;
            }

            $event->getExceptions()->add(
                new ProductLineItemInconsistentException($lineItemId)
            );
        }
    }

    /**
     * @param list<WriteCommand> $commands
     *
     * @return array<string, int>
     */
    private function findProducts(array $commands): array
    {
        $ids = array_map(function (WriteCommand $command) {
            if ($command->getDefinition()->getClass() !== OrderLineItemDefinition::class) {
                return null;
            }

            if ($command instanceof UpdateCommand) {
                return $command->getPrimaryKey()['id'];
            }

            return null;
        }, $commands);

        $ids = array_values(array_filter($ids));

        if (empty($ids)) {
            return [];
        }

        /** @var array<string, int> $products */
        $products = \array_flip($this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(id)) FROM order_line_item WHERE id IN (:ids) AND type = \'product\'',
            ['ids' => $ids],
            ['ids' => Connection::PARAM_STR_ARRAY]
        ));

        return $products;
    }

    /**
     * @param array<string, mixed> $products
     * @param array<string, mixed> $payload
     */
    private function isProduct(array $products, array $payload, string $lineItemId): bool
    {
        if (isset($payload['type'])) {
            return $payload['type'] === LineItem::PRODUCT_LINE_ITEM_TYPE;
        }

        return isset($products[$lineItemId]);
    }
}
