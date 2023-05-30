<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Event\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent;
use Shopware\Core\Content\ImportExport\Exception\ProcessingException;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncServiceInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 *
 * @phpstan-type CombinationPayload list<array{id: string, parentId: string, productNumber: string, stock: int, options: list<array{id: string, name: string, group: array{id: string, name: string}}>}>
 */
#[Package('system-settings')]
class ProductVariantsSubscriber implements EventSubscriberInterface, ResetInterface
{
    /**
     * @var array<string, string>
     */
    private array $groupIdCache = [];

    /**
     * @var array<string, string>
     */
    private array $optionIdCache = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly SyncServiceInterface $syncService,
        private readonly Connection $connection,
        private readonly EntityRepository $groupRepository,
        private readonly EntityRepository $optionRepository
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ImportExportAfterImportRecordEvent::class => 'onAfterImportRecord',
        ];
    }

    public function onAfterImportRecord(ImportExportAfterImportRecordEvent $event): void
    {
        $row = $event->getRow();
        $entityName = $event->getConfig()->get('sourceEntity');
        $entityWrittenEvents = $event->getResult()->getEvents();

        if ($entityName !== ProductDefinition::ENTITY_NAME || empty($row['variants']) || !$entityWrittenEvents) {
            return;
        }

        $variants = $this->parseVariantString($row['variants']);

        $entityWrittenEvent = $entityWrittenEvents->filter(fn ($event) => $event instanceof EntityWrittenEvent && $event->getEntityName() === ProductDefinition::ENTITY_NAME)->first();

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
            ['ids' => ArrayParameterType::STRING]
        );
        $configuratorSettingPayload = $this->getProductConfiguratorSettingPayload($payload, $parentId);
        $this->connection->executeStatement(
            'DELETE FROM `product_configurator_setting` WHERE `product_id` = :parentId AND `id` NOT IN (:ids);',
            [
                'parentId' => Uuid::fromHexToBytes($parentId),
                'ids' => Uuid::fromHexToBytesList(array_column($configuratorSettingPayload, 'id')),
            ],
            ['ids' => ArrayParameterType::STRING]
        );

        $this->syncService->sync([
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
        ], Context::createDefaultContext(), new SyncBehavior());
    }

    public function reset(): void
    {
        $this->groupIdCache = [];
        $this->optionIdCache = [];
    }

    /**
     * convert "size: m, l, xl" to ["size|m", "size|l", "size|xl"]
     *
     * @return list<array<string>>
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

            $options = array_map(fn ($option) => sprintf('%s|%s', $groupName, $option), $options);

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

    /**
     * @param list<array<string>> $variants
     *
     * @return CombinationPayload
     */
    private function getCombinationsPayload(array $variants, string $parentId, string $productNumber): array
    {
        $combinations = $this->getCombinations($variants);
        $payload = [];

        foreach ($combinations as $key => $combination) {
            $options = [];

            if (\is_string($combination)) {
                $combination = [$combination];
            }

            foreach ($combination as $option) {
                [$group, $option] = explode('|', $option);

                $optionId = $this->getOptionId($group, $option);
                $groupId = $this->getGroupId($group);

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
     *
     * @param list<array<string>> $variants
     *
     * @return list<array<string>>|array<string>
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
                $result[] = \is_array($combination) ? [...[$variant], ...$combination] : [$variant, $combination];
            }
        }

        return $result;
    }

    /**
     * @param CombinationPayload $variantsPayload
     *
     * @return list<array{id: string, optionId: string, productId: string}>
     */
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

    private function getGroupId(string $groupName): string
    {
        $groupId = Uuid::fromStringToHex($groupName);

        if (isset($this->groupIdCache[$groupId])) {
            return $this->groupIdCache[$groupId];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $groupName));

        $group = $this->groupRepository->search($criteria, Context::createDefaultContext())->first();

        if ($group !== null) {
            $this->groupIdCache[$groupId] = $group->getId();

            return $group->getId();
        }

        $this->groupIdCache[$groupId] = $groupId;

        return $groupId;
    }

    private function getOptionId(string $groupName, string $optionName): string
    {
        $optionId = Uuid::fromStringToHex(sprintf('%s.%s', $groupName, $optionName));

        if (isset($this->optionIdCache[$optionId])) {
            return $this->optionIdCache[$optionId];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $optionName));
        $criteria->addFilter(new EqualsFilter('group.name', $groupName));

        $option = $this->optionRepository->search($criteria, Context::createDefaultContext())->first();

        if ($option !== null) {
            $this->optionIdCache[$optionId] = $option->getId();

            return $option->getId();
        }

        $this->optionIdCache[$optionId] = $optionId;

        return $optionId;
    }
}
