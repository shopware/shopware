<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\Api;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\Message\RecalculatePricesForCurrencyMessage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class ChangeCurrencyController
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionInstanceRegistry;

    /**
     * @var MessageBusInterface
     */
    private $messageBus;

    /**
     * @var CacheClearer
     */
    private $cacheClearer;

    public function __construct(
        Connection $connection,
        DefinitionInstanceRegistry $definitionInstanceRegistry,
        MessageBusInterface $messageBus,
        CacheClearer $cacheClearer
    ) {
        $this->connection = $connection;
        $this->definitionInstanceRegistry = $definitionInstanceRegistry;
        $this->messageBus = $messageBus;
        $this->cacheClearer = $cacheClearer;
    }

    /**
     * @Route(path="/api/v{version}/_action/currency/change-default-currency/{currencyId}", methods={"POST"}, name="api.custom.currency.change-default")
     */
    public function change(string $currencyId): JsonResponse
    {
        $currencyId = strtolower($currencyId);
        $this->checkIsValidCurrencyId($currencyId);

        $defaultCurrencyId = Uuid::fromHexToBytes(Defaults::CURRENCY);

        $swapId = Uuid::randomBytes();

        $stmt = $this->connection->prepare('UPDATE currency SET id = :newId WHERE id = :oldId');

        $stmt->execute([
            'newId' => $swapId,
            'oldId' => $defaultCurrencyId,
        ]);

        $stmt->execute([
            'newId' => $defaultCurrencyId,
            'oldId' => Uuid::fromHexToBytes($currencyId),
        ]);

        $factor = (float) $this->connection->fetchColumn('SELECT factor FROM currency WHERE id = :id', ['id' => $defaultCurrencyId]);
        $this->connection->executeQuery(
            'UPDATE currency SET factor = IF(id = :id, 1, factor * :fixFactor);',
            [
                'id' => $defaultCurrencyId,
                'fixFactor' => 1 / $factor,
            ]
        );

        $this->schedulePriceUpdates($factor);

        $this->cacheClearer->invalidateTags([
            'entity_currency', // Invalidate entity reader
            'currency.id', // Invalidate entity searcher
        ]);

        return new JsonResponse(['id' => Defaults::CURRENCY]);
    }

    private function checkIsValidCurrencyId(string $currencyId): void
    {
        if ($currencyId === Defaults::CURRENCY) {
            throw new \RuntimeException('The given currencyId is already the default');
        }

        $exists = (bool) $this->connection->fetchColumn('SELECT 1 FROM currency WHERE id = UNHEX(?) LIMIT 1', [
            $currencyId,
        ]);

        if (!$exists) {
            throw new \RuntimeException('The given currencyId does not exists');
        }
    }

    private function schedulePriceUpdates(float $fixFactor): void
    {
        $tables = $this->getTablesAndFields();

        foreach ($tables as $table => $fields) {
            $ids = $this->connection->executeQuery('SELECT LOWER(HEX(id)) FROM ' . $table)->fetchAll(\PDO::FETCH_COLUMN);

            $chunks = array_chunk($ids, 50);

            foreach ($chunks as $chunk) {
                $msg = new RecalculatePricesForCurrencyMessage($table, $fields, $chunk, $fixFactor);
                $this->messageBus->dispatch($msg);
            }
        }
    }

    private function getTablesAndFields(): array
    {
        $updateTables = [];

        foreach ($this->definitionInstanceRegistry->getDefinitions() as $definition) {
            $fields = $definition->getFields()->filterInstance(PriceField::class);
            if ($fields->count()) {
                $updateTables[$definition->getEntityName()] = array_values($fields->fmap(static function (PriceField $field) {
                    return $field->getStorageName();
                }));
            }
        }

        return $updateTables;
    }
}
