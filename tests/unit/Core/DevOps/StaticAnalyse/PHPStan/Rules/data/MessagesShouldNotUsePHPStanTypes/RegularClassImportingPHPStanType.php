<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\MessagesShouldNotUsePHPStanTypes;

/**
 * @phpstan-import-type PrimaryKeyList from \Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\MessagesShouldNotUsePHPStanTypes\AsyncMessageUsingPHPStanType
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
