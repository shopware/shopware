<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Update\Services;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\Services\UpdateHtaccess;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class UpdateHtaccessTest extends TestCase
{
    private const INSTRUCTIONS = '# The directives (lines) between "# BEGIN Shopware" and "# END Shopware" are dynamically generated. Any changes to the directives between these markers will be overwritten.';

    private Filesystem $filesystem;

    private string $directory;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/' . uniqid('update-htaccess-test-', true);
        $this->filesystem->mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    #[DataProvider('getCombinations')]
    public function testCombination(string $currentHtaccess, string $newHtaccess, string $expected): void
    {
        $htaccess = $this->directory . '/.htaccess';
        $this->filesystem->dumpFile($htaccess, $currentHtaccess);
        $this->filesystem->dumpFile($htaccess . '.dist', $newHtaccess);

        (new UpdateHtaccess($htaccess))->update();

        static::assertStringEqualsFile($htaccess, $expected);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function getCombinations(): iterable
    {
        yield 'update marker' => [
            "# BEGIN Shopware\nOLD\n# END Shopware",
            "# BEGIN Shopware\nNEW\n# END Shopware",
            "# BEGIN Shopware\n" . self::INSTRUCTIONS . "\nNEW\n# END Shopware",
        ];

        yield 'update marker with pre and after lines' => [
            "BEFORE\n# BEGIN Shopware\nOLD\n# END Shopware\nAFTER",
            "# BEGIN Shopware\nNEW\n# END Shopware",
            "BEFORE\n# BEGIN Shopware\n" . self::INSTRUCTIONS . "\nNEW\n# END Shopware\nAFTER",
        ];

        yield 'update containing help text' => [
            "BEFORE\n# BEGIN Shopware\n" . self::INSTRUCTIONS . "\nOLD\n# END Shopware\nAFTER",
            "# BEGIN Shopware\n" . self::INSTRUCTIONS . "\nNEW\n# END Shopware",
            "BEFORE\n# BEGIN Shopware\n" . self::INSTRUCTIONS . "\nNEW\n# END Shopware\nAFTER",
        ];
    }

    public function testReplaceComplete(): void
    {
        $htaccess = $this->directory . '/.htaccess';
        $this->filesystem->copy(__DIR__ . '/_fixtures/htaccess', $htaccess);
        $newHtaccess = "# BEGIN Shopware\n" . self::INSTRUCTIONS . "\nNEW\n# END Shopware";
        $this->filesystem->dumpFile($htaccess . '.dist', $newHtaccess);

        (new UpdateHtaccess($htaccess))->update();

        static::assertStringEqualsFile($htaccess, $newHtaccess);
    }
}
