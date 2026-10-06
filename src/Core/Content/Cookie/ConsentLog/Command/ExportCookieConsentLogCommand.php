<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\ConsentLog\Command;

use Shopware\Core\Content\Cookie\ConsentLog\AbstractCookieConsentLogStorage;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentRecord;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\JsonStreamer\StreamWriterInterface;
use Symfony\Component\JsonStreamer\ValueTransformer\DateTimeToStringValueTransformer;
use Symfony\Component\TypeInfo\Type;

/**
 * Streams the consent log to stdout for compliance exports, e.g.
 * `bin/console cookie:consent:export --from=2026-01-01 --to=2026-07-01 --format=csv > consents.csv`
 *
 * @internal
 */
#[Package('framework')]
#[AsCommand(
    name: 'cookie:consent:export',
    description: 'Export the recorded cookie consent decisions as JSON or CSV',
)]
class ExportCookieConsentLogCommand extends Command
{
    private const FORMAT_JSON = 'json';
    private const FORMAT_CSV = 'csv';

    private const CSV_COLUMNS = ['consentId', 'createdAt', 'consentAction', 'salesChannelId', 'languageId', 'configHash', 'groupDecisions', 'acceptedCookies'];

    private const CSV_FLUSH_SIZE = 1000;

    private const JSON_BLOCK_SIZE = 65536;

    /**
     * @param StreamWriterInterface<array{include_null_properties?: bool, ...<string, mixed>}> $jsonWriter
     */
    public function __construct(
        private readonly AbstractCookieConsentLogStorage $storage,
        private readonly StreamWriterInterface $jsonWriter,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        OutputInterface $output,
        #[Option(description: 'Only decisions recorded at or after this date/time (inclusive)', name: 'from')]
        string $from = '1970-01-01',
        #[Option(description: 'Only decisions recorded before this date/time (exclusive), defaults to now', name: 'to')]
        ?string $to = null,
        #[Option(description: 'Only decisions of this sales channel id', name: 'sales-channel')]
        ?string $salesChannelId = null,
        #[Option(description: 'Output format: json (one array) or csv', name: 'format')]
        string $format = self::FORMAT_JSON,
    ): int {
        if (!\in_array($format, [self::FORMAT_JSON, self::FORMAT_CSV], true)) {
            $io->error(\sprintf('Unknown format "%s", expected "json" or "csv"', $format));

            return self::INVALID;
        }

        if ($salesChannelId !== null && !Uuid::isValid($salesChannelId)) {
            $io->error(\sprintf('"%s" is not a valid sales channel id', $salesChannelId));

            return self::INVALID;
        }

        try {
            $fromDate = new \DateTimeImmutable($from);
            $toDate = new \DateTimeImmutable($to ?? 'now');
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            return self::INVALID;
        }

        $records = $this->storage->iterate($fromDate, $toDate, $salesChannelId);

        if ($format === self::FORMAT_CSV) {
            $this->writeCsv($output, $records);
        } else {
            $this->writeJson($output, $records);
        }

        return self::SUCCESS;
    }

    /**
     * @param iterable<CookieConsentRecord> $records
     */
    private function writeJson(OutputInterface $output, iterable $records): void
    {
        $chunks = $this->jsonWriter->write(
            $records,
            Type::iterable(Type::object(CookieConsentRecord::class), Type::int()),
            [DateTimeToStringValueTransformer::FORMAT_KEY => \DateTimeInterface::RFC3339_EXTENDED],
        );

        $block = '';
        foreach ($chunks as $chunk) {
            $block .= $chunk;
            if (\strlen($block) >= self::JSON_BLOCK_SIZE) {
                $output->write($block, false, OutputInterface::OUTPUT_RAW);
                $block = '';
            }
        }

        $output->writeln($block, OutputInterface::OUTPUT_RAW);
    }

    /**
     * @param iterable<CookieConsentRecord> $records
     */
    private function writeCsv(OutputInterface $output, iterable $records): void
    {
        $buffer = fopen('php://temp', 'r+');
        \assert($buffer !== false);

        fputcsv($buffer, self::CSV_COLUMNS, escape: '');

        $count = 0;
        foreach ($records as $record) {
            $data = $record->jsonSerialize();
            $data['groupDecisions'] = json_encode($data['groupDecisions'], \JSON_THROW_ON_ERROR | \JSON_FORCE_OBJECT);
            $data['acceptedCookies'] = json_encode($data['acceptedCookies'], \JSON_THROW_ON_ERROR);

            fputcsv($buffer, array_map(static fn (string $column) => $data[$column], self::CSV_COLUMNS), escape: '');

            if (++$count % self::CSV_FLUSH_SIZE === 0) {
                $this->flushCsv($output, $buffer);
            }
        }

        $this->flushCsv($output, $buffer);
        fclose($buffer);
    }

    /**
     * Written raw, so the console formatter does not change values like "<info>"
     *
     * @param resource $buffer
     */
    private function flushCsv(OutputInterface $output, $buffer): void
    {
        rewind($buffer);
        $output->write((string) stream_get_contents($buffer), false, OutputInterface::OUTPUT_RAW);
        ftruncate($buffer, 0);
        rewind($buffer);
    }
}
