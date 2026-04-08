<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\StampedeProtectionConfigurator;
use Symfony\Component\Cache\LockRegistry;

/**
 * @internal
 */
class StampedeProtectionConfiguratorTest extends TestCase
{
    private const TEST_LOCK_FILES = ['/tmp/test-lock-1'];

    /**
     * @var list<string>
     */
    private array $originalLockFiles = [];

    protected function setUp(): void
    {
        // Save original state and set known test state
        $this->originalLockFiles = LockRegistry::setFiles(self::TEST_LOCK_FILES);
    }

    protected function tearDown(): void
    {
        // Restore original state
        LockRegistry::setFiles($this->originalLockFiles);
    }

    #[DataProvider('applyDataProvider')]
    public function testApply(bool $disableStampedeProtection, string $sessionSaveHandler, bool $expectFilesCleared): void
    {
        $configurator = new StampedeProtectionConfigurator($disableStampedeProtection, $sessionSaveHandler);
        $configurator->apply();

        $previousFiles = LockRegistry::setFiles(self::TEST_LOCK_FILES);

        if ($expectFilesCleared) {
            static::assertSame([], $previousFiles);
        } else {
            static::assertSame(self::TEST_LOCK_FILES, $previousFiles);
        }
    }

    /**
     * @return array<string, array{bool, string, bool}>
     */
    public static function applyDataProvider(): array
    {
        return [
            'disabled config' => [false, 'files', false],
            'enabled config, non-file session' => [true, 'redis', false],
            'enabled config, file session' => [true, 'files', true],
        ];
    }
}
