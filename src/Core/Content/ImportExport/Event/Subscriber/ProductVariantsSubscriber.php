<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Event\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent;
use Shopware\Core\Content\ImportExport\Exception\ProcessingException;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncService;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductVariantsSubscriber implements EventSubscriberInterface
{
    private SyncService $syncService;

    private Connection $connection;

    public function __construct(SyncService $syncService, Connection $connection)
    {
        $this->syncService = $syncService;
        $this->connection = $connection;
    }

    public static function getSubscribedEvents()
    {
        return [
            ImportExportAfterImportRecordEvent::class => 'onAfterImportRecord',
        ];
    }

    public function onAfterImportRecord(ImportExportAfterImportRecordEvent $event): void
    {
        if (!Feature::isActive('FEATURE_NEXT_8097')) {
            return;
        }

        $row = $event->getRow();
        $entityName = $event->getConfig()->get('sourceEntity');
        $entityWrittenEvents = $event->getResult()->getEvents();

        if ($entityName !== ProductDefinition::ENTITY_NAME || empty($row['variants']) || !$entityWrittenEvents) {
            return;
        }

        $variants = $this->parseVariantString($row['variants']);

        $entityWrittenEvent = $entityWrittenEvents->filter(function ($event) {
            return $event instanceof EntityWrittenEvent && $event->getEntityName() === ProductDefinition::ENTITY_NAME;
        })->first();

        if (!$entityWrittenEvent instanceof EntityWrittenEvent) {
            return;
        }

        $writeResults = $entityWrittenEvent->getWriteResults();

        if (empty($writeResults)) {
            return;
        }

        $parentId = $writeResults[0]->getPrimaryKey();
        $parentPayload = $writeResults[0]->getPayload();

        if (!\is_string($parentId)) {
            return;
        }

        $payload = $this->getCombinationsPayload($variants, $parentId, $parentPayload['productNumber']);
        $variantIds = array_column($payload, 'id');
        $this->connection->executeStatement(
            'DELETE FROM `product_option` WHERE `product_id` IN (:ids);',
            ['ids' => Uuid::fromHexToBytesList($variantIds)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        $configuratorSettingPayload = $this->getProductConfiguratorSettingPayload($payload, $parentId);
        $this->connection->executeStatement(
            'DELETE FROM `product_configurator_setting` WHERE `product_id` = :parentId AND `id` NOT IN (:ids);',
            [
                'parentId' => Uuid::fromHexToBytes($parentId),
                'ids' => Uuid::fromHexToBytesList(array_column($configuratorSettingPayload, 'id')),
            ],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $result = $this->syncService->sync([
            new SyncOperation(
                'write',
                ProductDefinition::ENTITY_NAME,
                SyncOperation::ACTION_UPSERT,
                $payload
            ),
            new SyncOperation(
                'write',
                ProductConfiguratorSettingDefinition::ENTITY_NAME,
                SyncOperation::ACTION_UPSERT,
                $configuratorSettingPayload
            ),
        ], $event->getContext(), new SyncBehavior(true, true));

        if (!$result->isSuccess()) {
            $operation = $result->get('write');

            throw new ProcessingException(sprintf(
                'Failed writing variants for %s with errors: %s',
                $parentPayload['productNumber'],
                $operation ? json_encode(array_column($operation->getResult(), 'errors')) : ''
            ));
        }
    }

    /**
     * convert "size: m, l, xl" to ["size|m", "size|l", "size|xl"]
     */
    private function parseVariantString(string $variantsString): array
    {
        $result = [];

        $groups = explode('|', $variantsString);

        foreach ($groups as $group) {
            $groupOptions = explode(':', $group);

            if (\count($groupOptions) !== 2) {
                $this->throwExceptionFailedParsingVariants($variantsString);
            }

            $groupName = trim($groupOptions[0]);
            $options = array_filter(array_map('trim', explode(',', $groupOptions[1])));

            if (empty($groupName) || empty($options)) {
                $this->throwExceptionFailedParsingVariants($variantsString);
            }

            $options = array_map(function ($option) use ($groupName) {
                return sprintf('%s|%s', $groupName, $option);
            }, $options);

            $result[] = $options;
        }

        return $result;
    }

    private function throwExceptionFailedParsingVariants(string $variantsString): void
    {
        throw new ProcessingException(sprintf(
            'Failed parsing variants from string "%s", valid format is: "size: L, XL, | color: Green, White"',
            $variantsString
        ));
    }

    private function getCombinationsPayload(array $variants, string $parentId, string $productNumber): array
    {
        $combinations = $this->getCombinations($variants);
        $payload = [];

        foreach ($combinations as $key => $combination) {
            $options = [];

            foreach ($combination as $option) {
                list($group, $option) = explode('|', $option);

                $optionId = Uuid::fromStringToHex(sprintf('%s.%s', $group, $option));
                $groupId = Uuid::fromStringToHex($group);

                $options[] = [
                    'id' => $optionId,
                    'name' => $option,
                    'group' => [
                        'id' => $groupId,
                        'name' => $group,
                    ],
                ];
            }

            $variantId = Uuid::fromStringToHex(sprintf('%s.%s', $parentId, $key));
            $variantProductNumber = sprintf('%s.%s', $productNumber, $key);

            $payload[] = [
                'id' => $variantId,
                'parentId' => $parentId,
                'productNumber' => $variantProductNumber,
                'stock' => 0,
                'options' => $options,
            ];
        }

        return $payload;
    }

    /**
     * convert [["size|m", "size|l"], ["color|blue", "color|red"]]
     * to [["size|m", "color|blue"], ["size|l", "color|blue"], ["size|m", "color|red"], ["size|l", "color|red"]]
     */
    private function getCombinations(array $variants, int $currentIndex = 0): array
    {
        if (!isset($variants[$currentIndex])) {
            return [];
        }

        if ($currentIndex === \count($variants) - 1) {
            return $variants[$currentIndex];
        }

        // get combinations from subsequent arrays
        $combinations = $this->getCombinations($variants, $currentIndex + 1);

        $result = [];

        // concat each array from tmp with each element from $variants[$i]
        foreach ($variants[$currentIndex] as $variant) {
            foreach ($combinations as $combination) {
                $result[] = \is_array($combination) ? array_merge([$variant], $combination) : [$variant, $combination];
            }
        }

        return $result;
    }

    private function getProductConfiguratorSettingPayload(array $variantsPayload, string $parentId): array
    {
        $options = array_merge(...array_column($variantsPayload, 'options'));
        $optionIds = array_unique(array_column($options, 'id'));

        $payload = [];

        foreach ($optionIds as $optionId) {
            $payload[] = [
                'id' => Uuid::fromStringToHex(sprintf('%s_configurator', $optionId)),
                'optionId' => $optionId,
                'productId' => $parentId,
            ];
        }

        return $payload;
    }
}
