<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Schema;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\CustomEntityException;

/**
 * Validates custom entity and field names before they are used as SQL identifiers.
 *
 * Entity and field names end up as SQL identifiers in the generated DDL, which Doctrine emits
 * unquoted. Restricting them to letters, digits, underscores, dollar signs and high bytes means
 * a name can never contain whitespace or the punctuation that could terminate an identifier, so
 * it cannot break out of its position and append arbitrary SQL.
 *
 * @internal
 */
#[Package('framework')]
class CustomEntityNameValidator
{
    // Matches an unquoted MySQL/MariaDB identifier: [0-9,a-z,A-Z$_] plus bytes >= 0x80 (UTF-8).
    // It blocks whitespace and punctuation (backtick, quote, `;`, `()`, `-`, `.`, `/`) that would
    // otherwise make the generated CREATE statement invalid.
    private const NAME_PATTERN = '/^[a-zA-Z0-9_$\x7f-\xff]+$/';

    /**
     * @param list<string> $fieldNames
     */
    public function validate(string $entityName, array $fieldNames): void
    {
        if (\preg_match(self::NAME_PATTERN, $entityName) !== 1) {
            throw CustomEntityException::invalidEntityName($entityName);
        }

        foreach ($fieldNames as $fieldName) {
            if (\preg_match(self::NAME_PATTERN, $fieldName) !== 1) {
                throw CustomEntityException::invalidFieldName($entityName, $fieldName);
            }
        }
    }
}
