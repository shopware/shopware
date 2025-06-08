<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\MessagesShouldNotUsePHPStanTypes;

/**
 * @phpstan-import-type PrimaryKeyList from AsyncMessageUsingPHPStanType
 */
class RegularClassImportingPHPStanType
{
    /**
     * @param PrimaryKeyList $primaryKeys
     */
    public function __construct(
        public readonly array $primaryKeys
    ) {
    }
}
