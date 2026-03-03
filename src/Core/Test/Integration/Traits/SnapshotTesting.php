<?php declare(strict_types=1);

namespace Shopware\Core\Test\Integration\Traits;

/**
 * Trait to snapshot test various file types (JSON, HTML, XML, PDF, etc.).
 *
 * On first run (UPDATE_SNAPSHOTS=1), writes the snapshot file.
 * On subsequent runs, asserts current output matches the stored snapshot.
 *
 * @internal
 */
trait SnapshotTesting
{
    final public const TYPE_JSON = 'json';
    final public const TYPE_HTML = 'html';
    final public const TYPE_XML = 'xml';
    final public const TYPE_PDF = 'pdf';

    /**
     * @param array<array{type: string, actual: array<mixed>|string, message?: string}> $assertions
     */
    protected function assertSnapshot(string $name, array $assertions): void
    {
        $updatedSnapshots = [];
        $typeConfig = $this->getTypeConfig();

        foreach ($assertions as $assertion) {
            static::assertArrayHasKey('type', $assertion);

            $type = $assertion['type'];
            $config = $typeConfig[$type] ?? [];

            static::assertNotEmpty($config);

            $updated = $this->doAssertSnapshot(
                $name,
                $assertion['actual'],
                $type,
                $assertion['message'] ?? \sprintf($config['message'], $name),
                $config['read'] ?? null,
                $config['transform'] ?? null,
            );

            if ($updated !== null) {
                $updatedSnapshots[] = $updated;
            }
        }

        if ($updatedSnapshots !== []) {
            $this->markTestIncomplete(\sprintf('Snapshots updated: %s', implode(', ', $updatedSnapshots)));
        }
    }

    /**
     * @return array<string, array{message: string, read?: callable, transform?: callable, write?: callable, normalize?: callable}>
     */
    protected function getTypeConfig(): array
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
            ],
            self::TYPE_PDF => [
                'message' => 'PDF snapshot mismatch: %s',
                'normalize' => self::normalizePdf(...),
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
    protected function doAssertSnapshot(
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
    protected function updateSnapshot(string $filePath, array|string $data): void
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

    protected function getSnapshotPath(string $name, string $extension): string
    {
        return \sprintf('%s/%s/snapshot.%s', $this->getSnapshotDirectory(), $name, $extension);
    }

    protected function getSnapshotDirectory(): string
    {
        $refClass = new \ReflectionClass(static::class);
        $dir = \dirname((string) $refClass->getFileName()) . '/_snapshots';

        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            static::fail(\sprintf('Failed to create snapshot directory: %s', $dir));
        }

        return $dir;
    }

    protected function isUpdateSnapshotsEnabled(): bool
    {
        $env = $_SERVER['UPDATE_SNAPSHOTS'] ?? '';

        return !\in_array($env, ['false', '-1', '0', ''], true);
    }

    /**
     * @throws \JsonException
     *
     * @return array<mixed>
     */
    protected static function transformJson(string $content): array
    {
        return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
    }

    protected static function normalizeHtml(string $content): string
    {
        // replace the date in the meta tag to avoid snapshot differences
        return \preg_replace(
            '/(<meta name="date" content=")(.*?)(")/i',
            '$1[date]$3',
            $content
        ) ?? $content;
    }

    protected static function normalizePdf(string $content): string
    {
        // remove xmp packet
        $content = (string) preg_replace('/<\\?xpacket.*?\\?>/is', '', $content);

        // remove metadata streams
        $content = (string) preg_replace('/\\d+\\s+\\d+\\s+obj\\s*<<[^>]*\\/Type\\s*\\/Metadata[^>]*>>.*?endobj/s', '', $content);
        $content = (string) preg_replace('/\\d+\\s+\\d+\\s+obj\\s*<<[^>]*\\/Subtype\\s*\\/XML[^>]*>>.*?endobj/s', '', $content);

        // remove creation/modification dates, producer and creator info
        $content = (string) preg_replace('/\/CreationDate\s*\(D:[^)]+\)/', '/CreationDate (D:00000000000000)/', $content);
        $content = (string) preg_replace('/\/ModDate\s*\(D:[^)]+\)/', '/ModDate (D:00000000000000)/', $content);
        $content = (string) preg_replace('/\\/Producer\\s*\\(.*?\\)/', '/Producer (REMOVED)/', $content);
        $content = (string) preg_replace('/\\/Creator\\s*\\(.*?\\)/', '/Creator (REMOVED)/', $content);

        // remove ids
        $content = (string) preg_replace('/\/ID\s*\[\s*<[^>]+>\s*<[^>]+>\s*]/', '/ID [<> <>]/', $content);

        // remove title and author info
        $content = (string) preg_replace('/\\/Title\\s*\\(.*?\\)/', '/Title ()', $content);
        $content = (string) preg_replace('/\\/Author\\s*\\(.*?\\)/', '/Author ()', $content);

        // normalize line endings and whitespace to avoid differences due to platform variations
        $content = (string) preg_replace('/\\r\\n|\\r/', "\n", $content);
        $content = (string) preg_replace('/[ \\t]+/', ' ', $content);

        // normalize startxref to avoid differences in file size
        return (string) preg_replace('/startxref\\s*\\d+\\s*%%EOF/', "startxref 0\n%%EOF", $content);
    }
}
