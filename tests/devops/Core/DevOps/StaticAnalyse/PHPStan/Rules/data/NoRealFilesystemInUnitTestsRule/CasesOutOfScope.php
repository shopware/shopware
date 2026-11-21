<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoRealFilesystemInUnitTestsRule;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
class CasesOutOfScope extends TestCase
{
    public function testFilesystem(): void
    {
        $dir = sys_get_temp_dir() . '/sw';
        mkdir($dir);
        (new Filesystem())->remove($dir);
    }
}
