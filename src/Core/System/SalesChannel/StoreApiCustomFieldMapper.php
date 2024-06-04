<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Contracts\Service\ResetInterface;

#[Package('core')]
class StoreApiCustomFieldMapper implements ResetInterface
{
    /**
     * @internal
     *
     * @param array<string, list<array{name: string, type: string}>>|null $mapping
     */
    public function __construct(
        private readonly Connection $connection,
        private ?array $mapping = null // Only set in unit tests to prevent DB access
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function map(string $entity, ParameterBag $bag): array
    {
        $data = [];

        foreach ($this->getMapping($entity) as $mapping) {
            $field = $mapping['name'];

            if ($bag->has($field)) {
                $value = $bag->get($field);

                $data[$field] = $this->decodeValue($mapping['type'], $value);
            }
        }

        return $data;
    }

    public function reset(): void
    {
        $this->mapping = null;
    }

    /**
     * @return list<array{name: string, type: string}>
     */
    private function getMapping(string $entity): array
    {
        if ($this->mapping === null) {
            $mapping = $this->connection->fetchAllAssociative('
SELECT
    custom_field_set_relation.entity_name,
    custom_field.name,
    custom_field.type
FROM custom_field
INNER JOIN custom_field_set on custom_field.set_id = custom_field_set.id
INNER JOIN custom_field_set_relation on custom_field_set.id = custom_field_set_relation.set_id
WHERE custom_field.allow_customer_write = 1
');

            /** @var array<string, list<array{name: string, type: string}>> $groupedMapping */
            $groupedMapping = FetchModeHelper::group($mapping);
            $this->mapping = $groupedMapping;
        }

        return $this->mapping[$entity] ?? [];
    }

    private function decodeValue(string $type, mixed $value): mixed
    {
        return match ($type) {
            CustomFieldTypes::BOOL, CustomFieldTypes::CHECKBOX, CustomFieldTypes::SWITCH => (bool) $value,
            CustomFieldTypes::INT, CustomFieldTypes::NUMBER => (int) $value,
            CustomFieldTypes::FLOAT => (float) $value,
            CustomFieldTypes::TEXT, CustomFieldTypes::HTML => (string) $value,
            CustomFieldTypes::SELECT, CustomFieldTypes::PRICE, CustomFieldTypes::JSON => $this->decodeArrayValues($value),
            CustomFieldTypes::DATETIME => new \DateTimeImmutable((string) $value),

            default => $value,
        };
    }

    private function decodeArrayValues(mixed $value): mixed
    {
        if ($value instanceof ParameterBag) {
            $value = $value->all();
        }

        return $value;
    }
}
