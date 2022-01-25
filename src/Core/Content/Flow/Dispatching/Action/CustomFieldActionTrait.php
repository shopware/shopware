<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Shopware\Core\Framework\Uuid\Uuid;

trait CustomFieldActionTrait
{
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
        $query = $this->connection->createQueryBuilder();
        $query->select('name');
        $query->from('custom_field');
        $query->innerJoin('custom_field', 'custom_field_set_relation', 'custom_field_set_relation', 'custom_field.set_id = custom_field_set_relation.set_id');
        $query->where('custom_field_set_relation.entity_name = :entity');
        $query->where('custom_field.id = :id');
        $query->setParameter('entity', $entity);
        $query->setParameter('id', Uuid::fromHexToBytes($customFieldId));
        $name = $query->execute()->fetchColumn();

        return $name === false ? null : $name;
    }
}
