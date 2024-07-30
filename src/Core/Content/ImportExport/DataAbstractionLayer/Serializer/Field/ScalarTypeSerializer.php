<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field;

use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\ImportExport\Processing\Mapping\Mapping;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('services-settings')]
final class ScalarTypeSerializer
{
    private const FILTER_VAR_DEFAULT_FLAGS = ['flags' => \FILTER_NULL_ON_FAILURE];

    public static function deserializeInt(Config $config, Field $field, string $value): ?int
    {
        $filtered = filter_var($value, \FILTER_VALIDATE_INT, self::FILTER_VAR_DEFAULT_FLAGS);

        if (\is_int($filtered)) {
            return $filtered;
        }

        $mapping = $config->getMapping()->get($field->getPropertyName());
        if (!$mapping instanceof Mapping) {
            return null;
        }

        if (self::isValidEmpty($value, $mapping->isRequiredByUser())) {
            return null;
        }

        throw ImportExportException::deserializationFailed($mapping->getMappedKey(), $value, 'integer');
    }

    public static function deserializeBool(Config $config, Field $field, string $value): ?bool
    {
        $filtered = filter_var($value, \FILTER_VALIDATE_BOOLEAN, self::FILTER_VAR_DEFAULT_FLAGS);

        if (\is_bool($filtered)) {
            return $filtered;
        }

        $mapping = $config->getMapping()->get($field->getPropertyName());
        if (!$mapping instanceof Mapping) {
            return null;
        }

        if (self::isValidEmpty($value, $mapping->isRequiredByUser())) {
            return null;
        }

        throw ImportExportException::deserializationFailed($mapping->getMappedKey(), $value, 'boolean');
    }

    private static function isValidEmpty(string $value, bool $isRequiredByUser): bool
    {
        return \trim($value) === '' && !$isRequiredByUser;
    }
}
