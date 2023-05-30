<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
trait EntityCustomFieldsTrait
{
    /**
     * @var array<mixed>|null
     */
    protected $customFields;

    /**
     * @return array<mixed>|null
     */
    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    /**
     * Easy accessor for custom fields.
     *
     * Returns an array with the field names as keys and the values as values will be returned.
     * If you pass multiple field names and one of the fields does not exist, the field will not be in the result.
     *
     * Example:
     * ```php
     * $entity->setCustomFields([
     *     'my_custom_field' => 'value',
     *     'my_other_custom_field' => 'value',
     * ]);
     *
     * $entity->getCustomFieldsValues('my_custom_field') === ['my_custom_field' => 'value'];
     *
     * $entity->getCustomFieldsValues('my_custom_field', 'my_other_custom_field') === [
     *    'my_custom_field' => 'value',
     *    'my_other_custom_field' => 'value',
     * ];
     *
     * $entity->getCustomFieldsValues('my_custom_field', 'my_other_custom_field', 'my_third_custom_field') === [
     *    'my_custom_field' => 'value',
     *    'my_other_custom_field' => 'value',
     * ];
     * ```
     *
     * @return array<string, mixed>
     */
    public function getCustomFieldsValues(string ...$fields): array
    {
        return \array_intersect_key($this->customFields ?? [], \array_flip($fields));
    }

    /**
     * Easy accessor for a single custom field value.
     *
     * If the field does not exist, null will be returned.
     *
     * Example:
     * ```php
     * $entity->getCustomFieldsValue('my_custom_field') === 'value';
     * ```
     */
    public function getCustomFieldsValue(string $field): mixed
    {
        return $this->customFields[$field] ?? null;
    }

    /**
     * @param array<mixed>|null $customFields
     */
    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }

    /**
     * Allows to change custom fields.
     *
     * If you pass only one field name, the value of the field will be changed.
     * If you pass multiple field names, an array with the field names as keys and the values as values will be changed.
     *
     * Example:
     * ```php
     * $entity->setCustomFields([
     *      'my_custom_field' => 'value',
     *      'my_other_custom_field' => 'value',
     * ]);
     *
     * $entity->changeCustomFields([
     *      'my_custom_field' => 'new value',
     * ]);
     *
     * $entity->getCustomFieldsValue('my_custom_field') === 'new value';
     *
     * $entity->changeCustomFields([
     *      'my_custom_field' => 'new value',
     *      'my_other_custom_field' => 'new value',
     * ]);
     *
     * $entity->getCustomFieldsValues('my_custom_field', 'my_other_custom_field') === [
     *      'my_custom_field' => 'new value',
     *      'my_other_custom_field' => 'new value',
     * ];
     * ```
     *
     * @param array<string, mixed> $customFields
     */
    public function changeCustomFields(array $customFields): void
    {
        $this->customFields = \array_replace($this->customFields ?? [], $customFields);
    }
}
