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

        if ($updatedSnapshots !== []) {
            $this->markTestIncomplete(\sprintf('Snapshots updated: %s', implode(', ', $updatedSnapshots)));
        }
    }

    /**
     * @return array<string, array{message: string, read?: callable, transform?: callable, write?: callable, normalize?: callable}>
     */
    private function getTypeConfig(): array
    {
        return [
            self::TYPE_JSON => [
                'message' => 'JSON snapshot mismatch: %s',
                'transform' => self::transformJson(...),
            ],
            self::TYPE_HTML => [
                'message' => 'HTML snapshot mismatch: %s',
                'normalize' => self::normalizeHtml(...),
            ],
            self::TYPE_XML => [
                'message' => 'XML snapshot mismatch: %s',
                'normalize' => self::normalizeXml(...),
            ],
        ];
    }

    /**
     * @param array<mixed>|string $actual
     *
     * @throws \JsonException
     *
     * @return string|null The snapshot identifier if it was updated, null otherwise
     */
    private function doAssertSnapshot(
        string $name,
        array|string $actual,
        string $extension,
        string $message,
        ?callable $transform = null,
        ?callable $normalize = null
    ): ?string {
        $filePath = $this->getSnapshotPath($name, $extension);

        if ($normalize !== null) {
            $actual = $normalize($actual);
        }

        if ($this->isUpdateSnapshotsEnabled()) {
            $this->updateSnapshot($filePath, $actual);

            return \sprintf('%s', $filePath);
        }

        if (!\is_file($filePath)) {
            $this->fail(\sprintf('Missing snapshot \'%s\'. Run with UPDATE_SNAPSHOTS=1 to generate it.', $filePath));
        }

        $expected = \file_get_contents($filePath);
        static::assertNotFalse($expected);

        if ($transform !== null) {
            $expected = $transform($expected);
        }

        static::assertSame($expected, $actual, $message);

        return null;
    }

    /**
     * @param array<mixed>|string $data
     *
     * @throws \JsonException
     */
    private function updateSnapshot(string $filePath, array|string $data): void
    {
        $dir = \dirname($filePath);

        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            static::fail(\sprintf('Failed to create snapshot directory: %s', $dir));
        }

        $content = \is_array($data)
            ? json_encode($data, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES) . \PHP_EOL
            : $data;

        file_put_contents($filePath, $content);
    }

    private function getSnapshotPath(string $name, string $extension): string
    {
        return \sprintf('%s/%s/snapshot.%s', $this->getSnapshotDirectory(), $name, $extension);
    }

    private function getSnapshotDirectory(): string
    {
        $refClass = new \ReflectionClass(static::class);
        $dir = \dirname((string) $refClass->getFileName()) . '/_snapshots';

        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            static::fail(\sprintf('Failed to create snapshot directory: %s', $dir));
        }

        return $dir;
    }

    private function isUpdateSnapshotsEnabled(): bool
    {
        $env = $_SERVER['UPDATE_SNAPSHOTS'] ?? '';

        return !\in_array($env, ['false', '-1', '0', ''], true);
    }

    /**
     * @throws \JsonException
     *
     * @return array<mixed>
     */
    private static function transformJson(string $content): array
    {
        return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
    }

    private static function normalizeHtml(string $content): string
    {
        // replace date meta data
        return \preg_replace(
            '/(<meta name="date" content=")(.*?)(")/i',
            '$1[date]$3',
            $content
        ) ?? $content;
    }

    private static function normalizeXml(string $content): string
    {
        // replace all date meta data
        return \preg_replace(
            '/(<(?:udt|qdt):DateTimeString format="102">)(\d{8})(<\/(?:udt|qdt):DateTimeString>)/',
            '$1[date]$3',
            $content
        ) ?? $content;
    }
}
