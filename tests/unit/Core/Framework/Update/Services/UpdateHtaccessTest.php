<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Update\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\Services\UpdateHtaccess;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UpdateHtaccess::class)]
class UpdateHtaccessTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame(
            [
                'Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent' => 'update',
            ],
            UpdateHtaccess::getSubscribedEvents()
        );
    }

    public function testUpdateIgnoresMissingHtaccess(): void
    {
        $htaccess = __DIR__ . '/_fixtures/UpdateHtaccess/missing-htaccess/htaccess';

        (new UpdateHtaccess($htaccess))->update();

        static::assertFileDoesNotExist($htaccess);
    }

    /**
     * The rewriting cases live in the integration test of the same name: they need a writable tree.
     */
    #[DataProvider('getUntouchedCombinations')]
    public function testCombination(string $fixture, string $expected): void
    {
        $htaccess = __DIR__ . '/_fixtures/UpdateHtaccess/' . $fixture . '/htaccess';

        (new UpdateHtaccess($htaccess))->update();

        static::assertStringEqualsFile($htaccess, $expected);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function getUntouchedCombinations(): iterable
    {
        yield 'dist file missing' => ['missing-dist', 'Test'];
        yield 'user has removed marker' => ['removed-marker', 'Test'];
    }
}
