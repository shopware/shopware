<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Coverage\Command;

use Composer\Console\Input\InputOption;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @internal
 */
#[AsCommand(
    name: 'coverage:js-files-per-area',
    description: 'Output all JS Files of the Shopware-namespace aggregated by area.

  In order for this command to work properly, you need to dump the composer autoloader before running it:
  $ composer dump-autoload -o'
)]
#[Package('core')]
class GetJSFilesPerAreaCommand extends Command
{
    public const OPTION_SEPARATED = 'separated';
    public const OPTION_DELIMITER = 'delimiter';
    public const OPTION_AREA = 'area';
    public const OPTION_RELATIVE = 'relative';
    public const OPTION_PREFIX_RELATIVE = 'prefix-relative';
    public const OPTION_IGNORE_FILES = 'ignore-files';
    private const ARGUMENT_PATH = 'path';

    protected function configure(): void
    {
        $this->addArgument(
            self::ARGUMENT_PATH,
            InputArgument::REQUIRED,
            'Absolute path to the JS files'
        );

        $this->addOption(
            self::OPTION_SEPARATED,
            's',
            InputOption::VALUE_NONE,
            'Output files separated'
        );

        $this->addOption(
            self::OPTION_DELIMITER,
            'd',
            InputOption::VALUE_OPTIONAL,
            'Delimiter used for separated output',
            '|'
        );

        $this->addOption(
            self::OPTION_AREA,
            'a',
            InputOption::VALUE_REQUIRED,
            'Specify the area for the output'
        );

        $this->addOption(
            self::OPTION_RELATIVE,
            'r',
            InputOption::VALUE_NONE,
            'Output paths relative to <path>'
        );

        $this->addOption(
            self::OPTION_PREFIX_RELATIVE,
            'p',
            InputOption::VALUE_REQUIRED,
            'Prefix the relative path'
        );

        $this->addOption(
            self::OPTION_IGNORE_FILES,
            'i',
            InputOption::VALUE_REQUIRED,
            'Ignore files from search'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getArgument('path');
        $areaToFile = [];
        $areaToFile['unknown'] = [];
        $ignoredFiles = $input->getOption(self::OPTION_IGNORE_FILES) ? explode(',', $input->getOption(self::OPTION_IGNORE_FILES)) : [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        /**
         * @var \RecursiveIteratorIterator<\RecursiveDirectoryIterator> $files
         * @var SplFileInfo $file
         */
        foreach ($files as $file) {
            $fileName = $file->getFilename();
            $filePath = $file->getRealPath();
            if ((\str_ends_with($fileName, '.js') || \str_ends_with($fileName, '.ts')) && !\str_ends_with($fileName, '.spec.js')) {
                if (\count($ignoredFiles) > 0) {
                    if (\in_array($filePath, $ignoredFiles, true)) {
                        continue;
                    }
                }
                $fileContent = \file_get_contents($filePath) ?: '';
                $matchCount = preg_match('/^.*@package (.*)$/m', $fileContent, $matches);
                $filePath = $input->getOption(self::OPTION_RELATIVE) ? $input->getOption(self::OPTION_PREFIX_RELATIVE) . str_replace($path, '', $filePath) : $filePath;
                if ($matchCount > 0) {
                    if (!\array_key_exists($matches[1], $areaToFile) || !\is_array($areaToFile[$matches[1]])) {
                        $areaToFile[$matches[1]] = [];
                    }
                    $areaToFile[$matches[1]][] = $filePath;
                } else {
                    $areaToFile['unknown'][] = $filePath;
                }
            }
        }

        if ($input->getOption(self::OPTION_AREA)) {
            $output->write(
                $input->getOption(self::OPTION_SEPARATED)
                    ? \implode($input->getOption(self::OPTION_DELIMITER) ?: '|', $areaToFile[$input->getOption(self::OPTION_AREA)])
                    : var_export($areaToFile[$input->getOption(self::OPTION_AREA)], true)
            );
        } else {
            $output->write(
                var_export($areaToFile, true)
            );
        }

        return 0;
    }
}
