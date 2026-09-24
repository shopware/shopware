<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\MyFakeNamespace;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class CrossClassQueryInLoop
{
    public function __construct(
        private readonly ProductLoader $loader,
        private readonly Formatter $formatter,
    ) {
    }

    /**
     * @param list<string> $ids
     *
     * @return list<array<string, mixed>>
     */
    public function loadPerId(array $ids): array
    {
        $result = [];

        foreach ($ids as $id) {
            $result[] = $this->loader->load($id);
        }

        return $result;
    }

    /**
     * A collaborator that does not query may be called per iteration.
     *
     * @param list<string> $ids
     *
     * @return list<string>
     */
    public function formatPerId(array $ids): array
    {
        $result = [];

        foreach ($ids as $id) {
            $result[] = $this->formatter->format($id);
        }

        return $result;
    }
}

/**
 * @internal
 */
class ProductLoader
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function load(string $id): array
    {
        $row = $this->connection->fetchAssociative('SELECT id FROM product WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);

        return $row === false ? [] : $row;
    }
}

/**
 * @internal
 */
class Formatter
{
    public function format(string $id): string
    {
        return strtoupper($id);
    }
}
