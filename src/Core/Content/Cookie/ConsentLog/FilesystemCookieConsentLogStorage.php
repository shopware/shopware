<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\ConsentLog;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\Cookie\CookieException;
use Shopware\Core\Framework\Log\Package;

/**
 * Keeps the consent log as files on the private filesystem, so the evidence stays out
 * of the shop database and can live on object storage through the Flysystem adapters.
 *
 * Layout below the configured path:
 *   <year>/<month>/<day>/<hour>/<timestamp>.<random>.<consentId>.json   one decision
 *   snapshots/<configHash>.json                                          one banner configuration
 *
 * One file per decision, so writes never append or lock. The consent id is part of the
 * file name, so the decisions of one visitor can be found with a plain file search. Hour
 * directories make the cleanup a directory delete, accurate to one hour.
 *
 * @internal
 */
#[Package('framework')]
final class FilesystemCookieConsentLogStorage extends AbstractCookieConsentLogStorage
{
    public const NAME = 'filesystem';

    private const SNAPSHOT_DIRECTORY = 'snapshots';

    private const FILE_EXTENSION = '.json';

    private const TIMESTAMP_FORMAT = 'Ymd\THisv\Z';

    /**
     * Directory depth of an hour directory: year, month, day, hour
     */
    private const HOUR_DEPTH = 4;

    private readonly string $path;

    public function __construct(
        private readonly FilesystemOperator $filesystem,
        string $path,
    ) {
        $this->path = trim($path, '/');
    }

    public function log(CookieConsentRecord $record): void
    {
        $this->ensureFileSafeConsentId($record->consentId);

        $createdAt = $record->createdAt->setTimezone(new \DateTimeZone('UTC'));
        $name = $createdAt->format(self::TIMESTAMP_FORMAT) . '.' . bin2hex(random_bytes(4)) . '.' . $record->consentId . self::FILE_EXTENSION;

        $this->filesystem->write(
            $this->path . '/' . $createdAt->format('Y/m/d/H') . '/' . $name,
            json_encode($record, \JSON_THROW_ON_ERROR),
        );
    }

    public function snapshot(CookieConsentConfigSnapshot $snapshot): void
    {
        $location = $this->snapshotLocation($snapshot->configHash);
        if ($this->filesystem->fileExists($location)) {
            return;
        }

        $this->filesystem->write($location, json_encode($snapshot, \JSON_THROW_ON_ERROR));
    }

    public function cleanup(\DateTimeImmutable $before): void
    {
        $this->prune($this->path, [], $before->setTimezone(new \DateTimeZone('UTC')));
    }

    public function iterate(\DateTimeImmutable $from, \DateTimeImmutable $to, ?string $salesChannelId = null): iterable
    {
        $from = $from->setTimezone(new \DateTimeZone('UTC'));
        $to = $to->setTimezone(new \DateTimeZone('UTC'));

        foreach ($this->hourDirectories($this->path, [], $from, $to) as $hourDirectory) {
            // The file names start with the timestamp, so sorting them yields chronological order
            $files = [];
            foreach ($this->filesystem->listContents($hourDirectory) as $item) {
                if ($item->isFile()) {
                    $files[] = $item->path();
                }
            }
            sort($files);

            foreach ($files as $file) {
                $record = $this->readRecord($file);
                if ($record->createdAt < $from || $record->createdAt >= $to) {
                    continue;
                }
                if ($salesChannelId !== null && $record->salesChannelId !== $salesChannelId) {
                    continue;
                }

                yield $record;
            }
        }
    }

    /**
     * Deletes every directory whose whole time range lies before $before, from the year
     * level down. A directory that is only partially expired is descended into, an hour
     * directory that is only partially expired is kept until the next run.
     *
     * @param list<int> $parts
     */
    private function prune(string $directory, array $parts, \DateTimeImmutable $before): void
    {
        foreach ($this->timeDirectories($directory) as $name => $location) {
            $childParts = [...$parts, $name];
            [$start, $end] = $this->rangeOf($childParts);

            if ($end <= $before) {
                $this->filesystem->deleteDirectory($location);

                continue;
            }

            if ($start >= $before || \count($childParts) === self::HOUR_DEPTH) {
                continue;
            }

            $this->prune($location, $childParts, $before);
        }

        if ($parts !== [] && $this->isEmpty($directory)) {
            $this->filesystem->deleteDirectory($directory);
        }
    }

    /**
     * Hour directories in chronological order, limited to the ones that overlap [$from, $to)
     *
     * @param list<int> $parts
     *
     * @return iterable<string>
     */
    private function hourDirectories(string $directory, array $parts, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): iterable
    {
        $children = $this->timeDirectories($directory);
        ksort($children, \SORT_NUMERIC);

        foreach ($children as $name => $location) {
            $childParts = [...$parts, $name];
            [$start, $end] = $this->rangeOf($childParts);

            if (($from !== null && $end <= $from) || ($to !== null && $start >= $to)) {
                continue;
            }

            if (\count($childParts) === self::HOUR_DEPTH) {
                yield $location;

                continue;
            }

            yield from $this->hourDirectories($location, $childParts, $from, $to);
        }
    }

    /**
     * Numeric sub directories, keyed by their name. Skips the snapshot directory.
     *
     * @return array<int, string>
     */
    private function timeDirectories(string $directory): array
    {
        $directories = [];
        foreach ($this->filesystem->listContents($directory) as $item) {
            $name = basename($item->path());
            if ($item->isDir() && ctype_digit($name)) {
                $directories[(int) $name] = $item->path();
            }
        }

        return $directories;
    }

    /**
     * Start (inclusive) and end (exclusive) of the period a directory covers
     *
     * @param list<int> $parts year, month, day, hour, as far as present
     *
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    private function rangeOf(array $parts): array
    {
        $start = new \DateTimeImmutable(\sprintf(
            '%04d-%02d-%02d %02d:00:00',
            $parts[0],
            $parts[1] ?? 1,
            $parts[2] ?? 1,
            $parts[3] ?? 0,
        ), new \DateTimeZone('UTC'));

        $unit = ['year', 'month', 'day', 'hour'][\count($parts) - 1];

        return [$start, $start->modify('+1 ' . $unit)];
    }

    private function isEmpty(string $directory): bool
    {
        foreach ($this->filesystem->listContents($directory) as $_item) {
            return false;
        }

        return true;
    }

    private function snapshotLocation(string $configHash): string
    {
        return $this->path . '/' . self::SNAPSHOT_DIRECTORY . '/' . $configHash . self::FILE_EXTENSION;
    }

    /**
     * The consent id becomes part of a file name, so a value outside the documented
     * pattern must never reach the filesystem
     */
    private function ensureFileSafeConsentId(string $consentId): void
    {
        if (preg_match(CookieConsentRecord::CONSENT_ID_PATTERN, $consentId) !== 1) {
            throw CookieException::invalidConsentId($consentId);
        }
    }

    private function readRecord(string $location): CookieConsentRecord
    {
        $data = $this->readJson($location);

        /** @var array<string, string> $groupDecisions */
        $groupDecisions = (array) $data['groupDecisions'];
        /** @var list<string> $acceptedCookies */
        $acceptedCookies = (array) $data['acceptedCookies'];

        return new CookieConsentRecord(
            consentId: (string) $data['consentId'],
            consentAction: CookieConsentAction::from((string) $data['consentAction']),
            groupDecisions: array_map(CookieConsentDecision::from(...), $groupDecisions),
            acceptedCookies: $acceptedCookies,
            configHash: (string) $data['configHash'],
            salesChannelId: (string) $data['salesChannelId'],
            languageId: (string) $data['languageId'],
            createdAt: new \DateTimeImmutable((string) $data['createdAt']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $location): array
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($this->filesystem->read($location), true, 512, \JSON_THROW_ON_ERROR);

        return $data;
    }
}
