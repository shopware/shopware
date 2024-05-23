<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Events\InvalidateProductCache;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class PriceStockImporter
{
    public function __construct(private readonly Connection $connection, private readonly EventDispatcherInterface $dispatcher)
    {
    }

    public function import(array $payload, Context $context): void
    {
        $payload = array_values($payload);

        $errors = $this->validate($payload);

        if (!empty($errors)) {
            throw ProductException::invalidPriceImportPayload($errors);
        }

        $this->connection->transactional(function () use ($payload): void {
            $insert = new MultiInsertQueryQueue($this->connection, 500);

            $stock = $this->connection->prepare('UPDATE product SET stock = :stock WHERE id = :id AND version_id = :version_id');

            $toRemove = [];
            foreach ($payload as $product) {
                if (isset($product['stock'])) {
                    $stock->execute([
                        'stock' => $product['stock'],
                        'id' => Uuid::fromHexToBytes($product['id']),
                        'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                    ]);
                }

                $pricing = $product['pricing'];
                if (!$pricing) {
                    continue;
                }

                $toRemove[] = $product['productId'];
                foreach ($pricing as $price) {
                    $insert->addInsert('product_pricing', $this->encode($price));
                }
            }

            $this->connection->executeQuery(
                'DELETE FROM product_pricing WHERE product_id IN (:ids) AND product_version_id = :version_id',
                ['ids' => Uuid::fromHexToBytesList($toRemove), 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
                ['ids' => ArrayParameterType::BINARY]
            );
            $insert->execute();
        });

        $ids = array_column($payload, 'id');

        $this->connection->executeStatement(
            'UPDATE product SET updated_at = :updated_at WHERE id IN (:ids) AND version_id = :version_id',
            ['ids' => Uuid::fromHexToBytesList($ids), 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION), 'updated_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $results = array_map(function ($id) {
            return new EntityWriteResult($id, [], 'product', EntityWriteResult::OPERATION_UPDATE);
        }, $ids);

        $this->dispatcher->dispatch(new EntityWrittenContainerEvent(
            context: $context,
            events: new NestedEventCollection([new EntityWrittenEvent('product', $results, $context)]),
            errors: []
        ));

        $this->dispatcher->dispatch(new InvalidateProductCache(ids: $ids, force: true));
    }

    private function validate(array $payload): array
    {
        $all = [];

        foreach ($payload as $index => $row) {
            $errors = [];
            if (!isset($row['productId'])) {
                $errors['productId'][] = 'Parameter is missing';
            }

            if (isset($row['stock']) && !\is_int($row['stock'])) {
                $errors['stock'][] = 'Parameter has to be an integer';
            }

            if (isset($row['pricing'])) {
                $errors['pricing'] = $this->validatePricing($row['pricing']);
            }

            // remove null values
            $errors = array_filter($errors);

            if (!empty($errors)) {
                $all[$index] = $errors;
            }
        }

        return $all;
    }

    private function validatePricing(array $rows): array
    {
        $prices = [];
        $discounts = [];
        $grouped = [];

        $all = [];
        foreach ($rows as $index => $row) {
            $hash = $this->hash($row);

            if (!isset($grouped[$hash])) {
                $grouped[$hash] = [];
            }
            if ($this->isPrice($row)) {
                $prices[$hash] = $index;
            }
            if ($this->isDiscount($row)) {
                $discounts[$hash] = $index;
            }
            $grouped[$hash][] = ['row' => $row, 'index' => $index];
        }

        foreach ($grouped as $hash => $group) {
            $group = $this->sortGroup($group);

            foreach ($group as $i => $item) {
                $errors = [];

                $index = $item['index'];
                $row = $item['row'];

                $before = $group[$i - 1] ?? null;
                $next = $group[$i + 1] ?? null;

                if (!$this->isPrice($row) && !$this->isDiscount($row)) {
                    $errors[] = 'Price or discount is missing';
                }

                if ($this->isPrice($row) && $row['quantityStart'] === null) {
                    $errors[] = 'Quantity start must be defined for price rows';
                }

                if ($this->isDiscount($row) && isset($prices[$hash])) {
                    $errors[] = sprintf('Price and discount are not allowed at the same time. Price is already defined in line %s', $prices[$hash] + 1);
                }

                if ($this->isPrice($row) && isset($discounts[$hash])) {
                    $errors[] = sprintf('Price and discount are not allowed at the same time. Discount is already defined in line %s', $discounts[$hash] + 1);
                }

                if ($this->isDiscount($row) && $this->isPrice($row)) {
                    $errors[] = sprtinf('Price and discount are not allowed at the same time. Discount is already defined in line %s', $discounts[$hash] + 1);
                }

                if (isset($row['salesChannelId']) && !isset($row['customerGroupId'])) {
                    $errors[] = 'Customer group is required if sales channel is defined';
                }

                if (isset($row['countryId']) && !isset($row['customerGroupId'])) {
                    $errors[] = 'Customer group is required if country is defined';
                }

                if (isset($row['countryId']) && !isset($row['salesChannelId'])) {
                    $errors[] = 'Sales channel is required if country is defined';
                }

                if ($row['quantityStart'] >= $row['quantityEnd'] && $row['quantityEnd'] !== null) {
                    $errors[] = 'Quantity start must be smaller than quantity end';
                }

                if ($before !== null && $row['quantityStart'] !== $before['row']['quantityEnd'] + 1) {
                    $errors[] = 'Quantity start of the row should be equal to the previous row.quantityEnd + 1';
                }

                if ($next === null && $row['quantityEnd'] !== null) {
                    $errors[] = 'Quantity end must be null for the last entry';
                }

                if (!empty($errors)) {
                    $all[$index] = $errors;
                }
            }
        }

        return $all;
    }

    private function sortGroup(array $group): array
    {
        usort($group, function ($a, $b) {
            if ($a['row']['quantityStart'] === null && $b['row']['quantityStart'] !== null) {
                return -1;
            }
            if ($a['row']['quantityStart'] !== null && $b['row']['quantityStart'] === null) {
                return 1;
            }

            return $a['row']['quantityStart'] - $b['row']['quantityStart'];
        });

        return $group;
    }

    private function isDiscount(array $row): bool
    {
        return isset($row['discount']) && $row['discount'] !== 0;
    }

    private function isPrice(array $row): bool
    {
        $system = $this->systemPrice($row);

        return $system !== null && $system['gross'] !== 0;
    }

    private function systemPrice(array $row)
    {
        if ($row['price'] === null) {
            return null;
        }

        foreach ($row['price'] as $value) {
            if ($value['currencyId'] === Defaults::CURRENCY) {
                return $value;
            }
        }

        return null;
    }

    private function hash(?array $price): string
    {
        $price = $price ?? [];

        return md5(json_encode([
            $price['customerGroupId'],
            $price['salesChannelId'],
            $price['countryId'],
        ]));
    }

    private function encode(array $row): array
    {
        $encoded = [];

        $encoded['id'] = Uuid::randomBytes();
        $encoded['product_version_id'] = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $encoded['product_id'] = Uuid::fromHexToBytes($row['productId']);
        $encoded['quantity_start'] = $row['quantityStart'] ?? null;
        $encoded['quantity_end'] = $row['quantityEnd'] ?? null;
        $encoded['price'] = $row['price'] ? Json::encode($row['price']) : null;
        $encoded['discount'] = $row['discount'] ?? null;
        $encoded['created_at'] = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        if (isset($row['customerGroupId'])) {
            $encoded['customer_group_id'] = Uuid::fromHexToBytes($row['customerGroupId']);
        }

        if (isset($row['salesChannelId'])) {
            $encoded['sales_channel_id'] = Uuid::fromHexToBytes($row['salesChannelId']);
        }

        if (isset($row['countryId'])) {
            $encoded['country_id'] = Uuid::fromHexToBytes($row['countryId']);
        }

        return $encoded;
    }
}
