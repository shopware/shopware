<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Contracts\Service\ResetInterface;

#[Package('core')]
class StoreApiCustomFieldMapper implements ResetInterface
{
    private ?array $mapping = null;

    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public function map(string $entity, ParameterBag $bag): array
    {
        $this->loadMapping();

        $data = [];

        foreach ($this->mapping[$entity] ?? [] as $field) {
            if ($bag->has($field)) {
                $data[$field] = $bag->get($field);
            }
        }

        return $data;
    }

    public function reset(): void
    {
        $this->mapping = null;
    }

    private function loadMapping(): void
    {
        if ($this->mapping !== null) {
            return;
        }

        $mapping = $this->connection->fetchAllAssociative('
SELECT
    custom_field_set_relation.entity_name,
    custom_field.name
FROM custom_field
INNER JOIN custom_field_set on custom_field.set_id = custom_field_set.id
INNER JOIN custom_field_set_relation on custom_field_set.id = custom_field_set_relation.set_id
WHERE custom_field.allow_customer_write = 1
');

        $this->mapping = [];

        foreach ($mapping as $item) {
            $this->mapping[$item['entity_name']][] = $item['name'];
        }
    }
}
