<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

/**
 * Breadcrumbs are stored as JSON objects in the DB, but represented as plain array in the API, therefore we need a specific type
 */
class BreadcrumbField extends JsonField
{
    /**
     * @param list<Field> $propertyMapping
     * @param array<mixed>|null $default
     */
    public function __construct(string $storageName = 'breadcrumb', string $propertyName = 'breadcrumb', array $propertyMapping = [], ?array $default = null)
    {
        parent::__construct($storageName, $propertyName, $propertyMapping, $default);
    }
}
