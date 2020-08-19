<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @deprecated tag:v6.4.0 - Backport will be dropped
 */
class CurrencyRoundingDeprecationUpdater implements EventSubscriberInterface
{
    /**
     * @var bool
     */
    private $blueGreenEnabled;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(bool $blueGreenEnabled, Connection $connection)
    {
        $this->blueGreenEnabled = $blueGreenEnabled;
        $this->connection = $connection;
    }

    public static function getSubscribedEvents()
    {
        return [
            CurrencyEvents::CURRENCY_WRITTEN_EVENT => 'updated',
        ];
    }

    public function updated(EntityWrittenEvent $event): void
    {
        if ($this->blueGreenEnabled) {
            return;
        }

        $backport = [];
        $port = [];
        foreach ($event->getPayloads() as $payload) {
            if (array_key_exists('decimalPrecision', $payload)) {
                $port[] = $payload['id'];

                continue;
            }

            if (array_key_exists('itemRounding', $payload)) {
                $backport[] = $payload['id'];

                continue;
            }
        }

        $this->port($port);

        $this->backport($backport);
    }

    private function port(array $ids): void
    {
        $ids = array_unique(array_filter($ids));

        if (empty($ids)) {
            return;
        }

        $currencies = $this->connection->fetchAll(
            'SELECT id, decimal_precision, item_rounding, total_rounding FROM currency WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $update = new RetryableQuery(
            $this->connection->prepare('UPDATE currency SET item_rounding = :item, total_rounding = :total WHERE id = :id')
        );

        foreach ($currencies as $currency) {
            $item = json_decode((string) $currency['item_rounding'], true);
            if (empty($item)) {
                $item = ['interval' => 0.01, 'roundForNet' => true];
            }

            $total = json_decode((string) $currency['total_rounding'], true);
            if (empty($total)) {
                $total = ['interval' => 0.01, 'roundForNet' => true];
            }

            $item['decimals'] = $currency['decimal_precision'];
            $total['decimals'] = $currency['decimal_precision'];

            $update->execute([
                'id' => $currency['id'],
                'total' => JsonFieldSerializer::encodeJson($total),
                'item' => JsonFieldSerializer::encodeJson($item),
            ]);
        }
    }

    private function backport(array $ids): void
    {
        $ids = array_unique(array_filter($ids));

        if (empty($ids)) {
            return;
        }

        $currencies = $this->connection->fetchAll(
            'SELECT id, decimal_precision, item_rounding, total_rounding FROM currency WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $update = new RetryableQuery(
            $this->connection->prepare('UPDATE currency SET decimal_precision = :decimals, total_rounding = :rounding WHERE id = :id')
        );

        foreach ($currencies as $currency) {
            $item = json_decode((string) $currency['item_rounding'], true);

            $rounding = $currency['total_rounding'] ?? $currency['item_rounding'];

            $update->execute([
                'id' => $currency['id'],
                'decimals' => $item['decimals'],
                'rounding' => $rounding,
            ]);
        }
    }
}
