<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('business-ops')]
trait CustomFieldActionTrait
{
    /**
     * @param array<string, mixed> $customFields
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>|null
     */
    public function getCustomFieldForUpdating(?array $customFields, array $config): ?array
    {
        $customFieldId = $config['customFieldId'];
        $customFieldValue = $config['customFieldValue'];

        if (empty($customFieldId) && empty($customFieldValue)) {
            return null;
        }

        $customFieldName = $this->getCustomFieldNameFromId($customFieldId, $config['entity']);

        if ($customFieldName === null) {
            return null;
        }

        $option = $config['option'] ?? 'overwrite';

        switch ($option) {
            case 'upsert':
                $customFields[$customFieldName] = $customFieldValue;

                break;
            case 'create':
                if (isset($customFields[$customFieldName])) {
                    return null;
                }

                $customFields[$customFieldName] = $customFieldValue;

                break;
            case 'clear':
                if (!isset($customFields[$customFieldName])) {
                    return null;
                }

                unset($customFields[$customFieldName]);

                break;
            case 'add':
                if (empty($customFieldValue)) {
                    return null;
                }

                $customFields[$customFieldName] = (array) ($customFields[$customFieldName] ?? []);
                $addData = array_diff((array) $customFieldValue, $customFields[$customFieldName]);

                if (empty($addData)) {
                    return null;
                }

                $customFields[$customFieldName] = array_merge($customFields[$customFieldName], $addData);

                break;
            case 'remove':
                if (!isset($customFields[$customFieldName]) || empty($customFieldValue)) {
                    return null;
                }

                $customFields[$customFieldName] = (array) ($customFields[$customFieldName] ?? []);
                $removeData = array_intersect($customFields[$customFieldName], (array) $customFieldValue);

                if (empty($removeData)) {
                    return null;
                }

                $customFields[$customFieldName] = array_values(array_diff($customFields[$customFieldName], $removeData));

                break;
            default:
                return null;
        }

        return $customFields;
    }

    private function getCustomFieldNameFromId(string $customFieldId, string $entity): ?string
    {
        $name = $this->connection->fetchOne(
            'SELECT name FROM custom_field INNER JOIN custom_field_set_relation ON  custom_field.set_id = custom_field_set_relation.set_id WHERE custom_field_set_relation.entity_name = :entity AND custom_field.id = :id',
            [
                'entity' => $entity,
                'id' => Uuid::fromHexToBytes($customFieldId),
            ]
        );

        return $name ?: null;
    }
}
