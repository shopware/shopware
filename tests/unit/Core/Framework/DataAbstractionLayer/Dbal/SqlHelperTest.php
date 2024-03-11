<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\SqlHelper;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(SqlHelper::class)]
class SqlHelperTest extends TestCase
{
    public function testObject(): void
    {
        $sql = SqlHelper::object(['foo' => 'bar', 'foe' => 'boe'], 'table');

        static::assertSame('JSON_OBJECT(\'foo\', bar,\'foe\', boe) as table', $sql);
    }

    public function testObjectArray(): void
    {
        $sql = SqlHelper::objectArray(['foo' => 'bar', 'foe' => 'boe'], 'table');

        static::assertSame('CONCAT(
    \'[\',
         GROUP_CONCAT(DISTINCT
             JSON_OBJECT(
                \'foo\', bar,\'foe\', boe
             )
         ),
    \']\'
) as table', $sql);
    }
}
