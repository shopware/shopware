<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Subscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\DataAbstractionLayer\StatesUpdater;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - reason:remove-subscriber - temporary one-time digital product states regression
 */
#[Package('inventory')]
final readonly class RepairDigitalProductStatesSubscriber implements EventSubscriberInterface
{
    /**
     * @deprecated tag:v6.8.0 - reason:remove-subscriber - temporary one-time repair marker
     */
    public const REPAIRED_DIGITAL_PRODUCT_STATES = 'core.repaired_digital_product_states';

    private const BATCH_SIZE = 500;

    /**
     * @internal
     */
    public function __construct(
        private Connection $connection,
        private ?StatesUpdater $statesUpdater,
        private AbstractKeyValueStorage $storage,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<class-string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            UpdatePostFinishEvent::class => 'repair',
        ];
    }

    /**
     * @deprecated tag:v6.8.0 - reason:remove-subscriber - temporary one-time repair for 6.7.8.0 digital product states regression
     */
    public function repair(UpdatePostFinishEvent $event): void
    {
        if ($this->storage->has(self::REPAIRED_DIGITAL_PRODUCT_STATES)) {
            return;
        }

        if ($this->statesUpdater === null) {
            $this->storage->set(self::REPAIRED_DIGITAL_PRODUCT_STATES, '1');

            return;
        }

        try {
            $repaired = 0;
            $previousChunk = null;

            while (true) {
                /** @var list<string> $ids */
                $ids = $this->connection->fetchFirstColumn(
                    <<<'SQL'
                    SELECT LOWER(HEX(`id`)) AS id
                    FROM `product`
                    WHERE `version_id` = :versionId
                      AND `type` = :type
                      AND `states` IS NULL
                    LIMIT :limit
                    SQL,
                    [
                        'type' => ProductDefinition::TYPE_DIGITAL,
                        'limit' => self::BATCH_SIZE,
                        'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                    ],
                    [
                        'limit' => ParameterType::INTEGER,
                    ]
                );

                if ($ids === []) {
                    break;
                }

                if ($previousChunk === $ids) {
                    $this->logger->error('Aborting digital product states repair because the same product chunk was fetched repeatedly', [
                        'chunkSize' => \count($ids),
                        'productIds' => $ids,
                    ]);

                    break;
                }

                $previousChunk = $ids;

                $this->statesUpdater->update($ids, $event->getContext());
                $repaired += \count($ids);

                if (\count($ids) < self::BATCH_SIZE) {
                    break;
                }
            }

            $this->storage->set(self::REPAIRED_DIGITAL_PRODUCT_STATES, '1');

            if ($repaired > 0) {
                $event->appendPostUpdateMessage(\sprintf(
                    'Repaired product states for %d digital product(s) with missing legacy states.',
                    $repaired
                ));
            }
        } catch (\Throwable $exception) {
            // Must not break update flow.
            $this->logger->error('Failed to repair digital product states', ['exception' => $exception]);
        }
    }
}
