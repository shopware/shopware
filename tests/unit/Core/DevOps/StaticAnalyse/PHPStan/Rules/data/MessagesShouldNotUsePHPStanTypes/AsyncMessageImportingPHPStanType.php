<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\MessagesShouldNotUsePHPStanTypes;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @phpstan-import-type PrimaryKeyList from \Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\MessagesShouldNotUsePHPStanTypes\AsyncMessageUsingPHPStanType
 */
class AsyncMessageImportingPHPStanType implements AsyncMessageInterface
{
    /**
     * @param PrimaryKeyList $primaryKeys
     */
    public function __construct(
        public readonly array $primaryKeys
    ) {
    }
}
