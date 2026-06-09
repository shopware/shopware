<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Console;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shared handling for the standardized `--format` output option used by list-style commands.
 *
 * Commands declare which formats they support via {@see addFormatOption()} and resolve the
 * selected value via {@see resolveFormat()}. Deprecated per-command aliases (e.g. `--json`)
 * are handled by the command itself before calling {@see resolveFormat()}.
 */
#[Package('framework')]
trait OutputFormatTrait
{
    protected const FORMAT_TABLE = 'table';
    protected const FORMAT_JSON = 'json';

    /**
     * @param list<string> $formats the output formats this command supports
     */
    protected function addFormatOption(array $formats, string $default = self::FORMAT_TABLE): void
    {
        $this->addOption(
            'format',
            null,
            InputOption::VALUE_REQUIRED,
            \sprintf('Output format. Available options: %s', implode(', ', array_map(static fn (string $format) => \sprintf('"%s"', $format), $formats))),
            $default,
            $formats
        );
    }

    /**
     * Returns the resolved format, or null if an invalid format was given (an error is printed in that case).
     *
     * @param list<string> $formats the output formats this command supports
     */
    protected function resolveFormat(InputInterface $input, OutputInterface $output, array $formats): ?string
    {
        $format = $input->getOption('format');
        if (!\in_array($format, $formats, true)) {
            (new SymfonyStyle($input, $output))->error(\sprintf('Invalid format "%s". Allowed formats: %s', (string) $format, implode(', ', $formats)));

            return null;
        }

        return $format;
    }
}
