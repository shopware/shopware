<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Filter;

use Shopware\Core\Framework\Uuid\Uuid;

class AntiJoinFilter extends MultiFilter
{
    /**
     * @var string
     */
    private $id;

    public function __construct(string $operator, array $queries = [], ?string $id = null)
    {
        foreach ($queries as $query) {
            if ($query instanceof MultiFilter) {
                throw new \InvalidArgumentException(self::class . 'may only non terminal filters');
            }
        }
        parent::__construct($operator, $queries);

        $this->id = $id ?: Uuid::randomHex();
    }

    public function getIdentifier(): string
    {
        return $this->id;
    }

    public function getFields(): array
    {
        return [];
    }
}
