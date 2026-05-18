<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Service;

use Shopware\Core\Content\ImportExportV2\Exception\ImportExportV2Exception;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Content\ImportExportV2\Record\ImportExportRecord;
use Shopware\Core\Framework\Log\Package;

/**
 * Validates one imported record against the profile contract before we try to
 * map it into a DAL write payload.
 *
 * The readers only translate file formats into a shared record shape. This
 * validator acts as a whitelist check on the imported payload:
 *
 * - the record must target the profile entity
 * - the payload may only contain record paths that the profile explicitly
 *   allows
 * - wildcard list paths such as `tags.*.name` and `categoryTree.*` must keep a
 *   compatible structure
 *
 * It does not require that every profile path is present in every imported
 * record.
 *
 * Example:
 *
 * Allowed profile paths:
 * - `productNumber`
 * - `tax.id`
 * - `tags.*.name`
 *
 * Valid payload:
 * ```php
 * [
 *     'productNumber' => 'SW10001',
 *     'tax' => ['id' => 'tax-123'],
 *     'tags' => [
 *         ['name' => 'Featured'],
 *         ['name' => 'Sale'],
 *     ],
 * ]
 * ```
 *
 * Invalid payload:
 * ```php
 * [
 *     'productNumber' => 'SW10001',
 *     'tax' => ['id' => 'tax-123'],
 *     'tags' => [
 *         ['id' => 'tag-1'],
 *     ],
 * ]
 * ```
 *
 * because `tags.*.id` is not part of the allowed profile paths above.
 *
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ImportRecordValidator
{
    public function validate(ImportExportRecord $record, ImportExportV2ProfileEntity $profile): ImportExportRecord
    {
        if ($record->entity !== $profile->getEntity()) {
            throw ImportExportV2Exception::invalidImportRecord(\sprintf(
                'Record entity "%s" does not match profile entity "%s".',
                $record->entity,
                $profile->getEntity()
            ));
        }

        $allowedPaths = $profile->getRecordPaths();
        $actualPaths = $this->collectPayloadPaths($record->payload);

        foreach ($actualPaths as $path) {
            if (\in_array($path, $allowedPaths, true)) {
                continue;
            }

            throw ImportExportV2Exception::invalidImportRecord(\sprintf(
                'Record path "%s" is not allowed by profile "%s".',
                $path,
                $profile->getTechnicalName()
            ));
        }

        return $record;
    }

    /**
     * Converts a nested payload into profile-style record paths.
     *
     * Example:
     * ```php
     * [
     *     'productNumber' => 'SW10001',
     *     'tax' => ['id' => 'tax-123'],
     *     'tags' => [
     *         ['name' => 'Featured'],
     *         ['name' => 'Sale'],
     *     ],
     *     'categoryTree' => ['cat-1', 'cat-2'],
     * ]
     * ```
     *
     * becomes:
     * - `productNumber`
     * - `tax.id`
     * - `tags.*.name`
     * - `categoryTree.*`
     *
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    private function collectPayloadPaths(array $payload): array
    {
        $paths = [];

        foreach ($payload as $key => $value) {
            if (!\is_string($key) && !\is_int($key)) {
                throw ImportExportV2Exception::invalidImportRecord('Record payload keys must be strings or integers.');
            }

            $segment = (string) $key;
            $this->collectValuePaths($paths, $segment, $value);
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param list<string> $paths
     */
    private function collectValuePaths(array &$paths, string $path, mixed $value): void
    {
        if (!\is_array($value)) {
            $paths[] = $path;

            return;
        }

        if ($value === []) {
            return;
        }

        if (array_is_list($value)) {
            foreach ($value as $item) {
                if (\is_array($item)) {
                    if ($item === []) {
                        continue;
                    }

                    foreach ($item as $childKey => $childValue) {
                        if (!\is_string($childKey) && !\is_int($childKey)) {
                            throw ImportExportV2Exception::invalidImportRecord('List item keys must be strings or integers.');
                        }

                        $this->collectValuePaths($paths, $path . '.*.' . $childKey, $childValue);
                    }

                    continue;
                }

                $paths[] = $path . '.*';
            }

            return;
        }

        foreach ($value as $childKey => $childValue) {
            if (!\is_string($childKey) && !\is_int($childKey)) {
                throw ImportExportV2Exception::invalidImportRecord('Nested record keys must be strings or integers.');
            }

            $this->collectValuePaths($paths, $path . '.' . $childKey, $childValue);
        }
    }
}
