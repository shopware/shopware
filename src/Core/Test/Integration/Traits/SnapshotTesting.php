<?php declare(strict_types=1);

namespace Shopware\Core\Test\Integration\Traits;

/**
 * Trait to snapshot test JSON arrays or Twig-rendered HTML strings.
 *
 * On first run (UPDATE_SNAPSHOTS=1), writes the snapshot file (.json or .html).
 * On subsequent runs, asserts current output matches the stored snapshot.
 *
 * @internal
 */
trait SnapshotTesting
{
    /**
     * @param array<mixed>|string $actual Array data to encode as JSON, or HTML string.
     *
     * @throws \JsonException
     */
    private function assertSnapshot(string $name, array|string $actual, string $message = ''): void
    {
        $snapshotDir = $this->getSnapshotDirectory();

        $isHtml = \is_string($actual);
        $extension = $isHtml ? 'html' : 'json';
        $filePath = "$snapshotDir/$name.$extension";

        $update = ($_SERVER['UPDATE_SNAPSHOTS'] ?? '') === '1';

        if ($update) {
            file_put_contents($filePath, $this->prepareContent($actual, $isHtml));
            $this->markTestIncomplete("Snapshot updated: $name.$extension");
        }

        if (!file_exists($filePath)) {
            $this->fail("Missing snapshot '$name.$extension'. Run with UPDATE_SNAPSHOTS=1 to generate it.");
        }

        $stored = file_get_contents($filePath);

        if ($isHtml) {
            static::assertSame(
                trim($stored),
                trim(preg_replace('/\s+/', ' ', $actual)),
                $message ?: "HTML snapshot mismatch: $name"
            );
        } else {
            $baseline = json_decode($stored, true, 512, \JSON_THROW_ON_ERROR);
            static::assertSame(
                $baseline,
                $actual,
                $message ?: "JSON snapshot mismatch: $name"
            );
        }
    }

    private function getSnapshotDirectory(): string
    {
        $refClass = new \ReflectionClass(static::class);
        $dir = \dirname((string) $refClass->getFileName()) . '/_snapshots';

        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException("Failed to create snapshot directory: $dir");
        }

        return $dir;
    }

    /**
     * @throws \JsonException
     */
    private function prepareContent(array|string $data, bool $isHtml): string
    {
        if ($isHtml) {
            // normalize whitespace
            return trim(preg_replace('/\s+/', ' ', $data)) . \PHP_EOL;
        }

        return json_encode($data, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES) . \PHP_EOL;
    }
}
