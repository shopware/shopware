<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel\Fixture;

use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class ListingTestData
{
    /**
     * @var array<string>
     */
    protected array $ids = [];

    public function getId(string $key): string
    {
        return $this->ids[$key];
    }

    public function createId(string $key): string
    {
        return $this->ids[$key] = Uuid::randomHex();
    }

    public function getKey(string $id): string
    {
        $ids = array_flip($this->ids);

        return $ids[$id];
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string>
     */
    public function getKeyList(array $ids): array
    {
        $keys = [];
        $flipped = array_flip($this->ids);
        foreach ($ids as $id) {
            $keys[] = $flipped[$id];
        }

        return $keys;
    }

    /**
     * @return array<string>
     */
    public function all(): array
    {
        return $this->ids;
    }
}
