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
     * @param array<mixed>|string $actual
     * @param array<string, mixed> $options
     *
     * @throws \JsonException
     */
    protected function doAssertSnapshot(string $name, array|string $actual, string $extension, string $message, array $options = []): void
    {
        if ($extension === 'html' && ($options['normalizeWhitespace'] ?? false) && \is_string($actual)) {
            $actual = $this->normalizeHtmlWhitespace($actual);
        }

        $filePath = $this->getSnapshotPath($name, $extension);

        if ($this->isUpdateSnapshotsEnabled()) {
            $this->updateSnapshot($filePath, $actual);
            $this->markTestIncomplete(\sprintf('Snapshot updated: %s.%s', $name, $extension));
        }

        if (!file_exists($filePath)) {
            $this->fail(\sprintf('Missing snapshot \'%s.%s\'. Run with UPDATE_SNAPSHOTS=1 to generate it.', $name, $extension));
        }

        $expected = file_get_contents($filePath);
        \assert(\is_string($expected));

        if ($extension === 'json') {
            $expected = json_decode($expected, true, 512, \JSON_THROW_ON_ERROR);
        }

        if ($extension === 'html' && ($options['normalizeWhitespace'] ?? false)) {
            $expected = $this->normalizeHtmlWhitespace($expected);
        }

        static::assertSame($expected, $actual, $message);
    }

    /**
     * @throws \JsonException
     */
    protected function updateSnapshot(string $filePath, array|string $data): void
    {
        $content = \is_array($data)
            ? json_encode($data, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES) . \PHP_EOL
            : $data;

        file_put_contents($filePath, $content);
    }

    protected function getSnapshotPath(string $name, string $extension): string
    {
        return \sprintf('%s/%s.%s', $this->getSnapshotDirectory(), $name, $extension);
    }

    protected function getSnapshotDirectory(): string
    {
        $refClass = new \ReflectionClass(static::class);
        $dir = \dirname((string) $refClass->getFileName()) . '/_snapshots';

        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException(\sprintf('Failed to create snapshot directory: %s', $dir));
        }

        return $dir;
    }

    protected function isUpdateSnapshotsEnabled(): bool
    {
        $env = $_SERVER['UPDATE_SNAPSHOTS'] ?? '';

        return !\in_array($env, [false, -1, 0, ''], true);
    }

    protected function normalizeHtmlWhitespace(string $html): string
    {
        $lines = explode(\PHP_EOL, $html);
        $trimmedLines = array_map('trim', $lines);
        $nonEmptyLines = array_filter($trimmedLines, static fn (string $line) => $line !== '');
        $imploded = implode(\PHP_EOL, $nonEmptyLines);

        return preg_replace('/(?:(?:\r\n|\r|\n)\s*){2,}/', \PHP_EOL . \PHP_EOL, $imploded) ?? $imploded;
    }

    /**
     * @param array<mixed> $actual
     *
     * @throws \JsonException
     */
    private function assertJsonSnapshot(string $name, array $actual, ?string $message = null, array $options = []): void
    {
        $this->doAssertSnapshot(
            $name,
            $actual,
            'json',
            $message ?: "JSON snapshot mismatch: $name",
            $options,
        );
    }

    private function assertHtmlSnapshot(string $name, string $actual, ?string $message = null, array $options = []): void
    {
        $this->doAssertSnapshot(
            $name,
            $actual,
            'html',
            $message ?: "HTML snapshot mismatch: $name",
            $options,
        );
    }
}
