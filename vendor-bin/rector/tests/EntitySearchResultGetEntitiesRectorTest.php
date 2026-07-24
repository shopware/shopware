<?php declare(strict_types=1);

namespace Shopware\Tests\Rector;

use PHPUnit\Framework\Attributes\DataProvider;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @see \Shopware\Core\DevOps\StaticAnalyze\Rector\EntitySearchResultGetEntitiesRector
 */
#[Package('framework')]
class EntitySearchResultGetEntitiesRectorTest extends AbstractRectorTestCase
{
    #[DataProvider('provideData')]
    public function test(string $filePath): void
    {
        $this->doTestFile($filePath);
    }

    public static function provideData(): \Iterator
    {
        return self::yieldFilesFromDirectory(__DIR__ . '/Fixture');
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__ . '/config/configured_rule.php';
    }
}
