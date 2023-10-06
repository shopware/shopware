<?php declare(strict_types=1);

namespace Shopware\Core\Test\Integration\Traits;

/**
 * This trait allows to run an assertion on a stored snapshot.
 * During normal test operation the $actual parameter is ignored,
 * and the data stored in the snapshot file is used.
 * But when the env variable 'UPDATE_SNAPSHOTS' is set to 1,
 * the $actual parameter is written to the snapshot file.
 *
 * @internal
 */
trait SnapshotTesting
{
    /**
     * @param array<string, mixed>|list<mixed> $actual
     */
    private function assertSnapshot(string $expectedSnapshotName, array $actual, string $message = ''): void
    {
        $class = new \ReflectionClass(static::class);
        $baseDir = \dirname((string) $class->getFileName());
        $snapshot = $baseDir . '/_snapshots/' . $expectedSnapshotName . '.json';
        $envVar = $_SERVER['UPDATE_SNAPSHOTS'] ?? '';
        $runUpdate = $envVar !== 'false'
            && $envVar !== '-1'
            && $envVar !== '';

        if ($runUpdate) {
            file_put_contents(
                $snapshot,
                json_encode($actual, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES)
            );

            return;
        }

        $baseline = json_decode((string) file_get_contents($snapshot), true, \JSON_THROW_ON_ERROR, \JSON_THROW_ON_ERROR);
        static::assertSame($baseline, $actual, $message);
    }
}
