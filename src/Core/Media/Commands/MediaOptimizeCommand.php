<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Media\Commands;

use Shopware\Media\Exception\OptimizerNotFoundException;
use Shopware\Media\Optimizer\OptimizerServiceInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

class MediaOptimizeCommand extends Command
{
    /**
     * @var OptimizerServiceInterface
     */
    private $optimizerService;

    /**
     * @var string
     */
    private $mediaFilesystemPath;

    /**
     * @var SymfonyStyle
     */
    private $io;

    public function __construct(OptimizerServiceInterface $optimizerService, string $mediaFilesystemPath)
    {
        parent::__construct();

        $this->optimizerService = $optimizerService;
        $this->mediaFilesystemPath = $mediaFilesystemPath;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('media:optimize')
            ->setHelp('The <info>%command.name%</info> optimizes your uploaded media using external tools. You can check the availability using the <info>--info</info> option.')
            ->setDescription('Optimize uploaded media without quality loss.')
            ->addArgument('path', InputArgument::OPTIONAL, 'Path to your media folder')
            ->addOption('info', 'i', InputOption::VALUE_NONE, 'Display available tools')
            ->addOption('skip-scan', null, InputOption::VALUE_NONE, 'Skips the initial filesystem scan.')
            ->addOption('modified', 'm', InputOption::VALUE_REQUIRED, 'Limits the files modify date to the provided time string.')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        if ($input->getOption('info')) {
            $this->displayCapabilities();

            return null;
        }

        $finder = $this->createMediaFinder($input, $output);

        $numberOfFiles = 0;
        if (!$input->getOption('skip-scan')) {
            $numberOfFiles = $finder->count();
        }

        $this->io->progressStart($numberOfFiles);

        foreach ($finder->getIterator() as $file) {
            $this->io->progressAdvance();

            if ($output->getVerbosity() === OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln(' - ' . $file->getRelativePathname());
            }

            try {
                $this->optimizerService->optimize($file->getFilename());
            } catch (OptimizerNotFoundException $exception) {
                // empty catch intended since no optimizer is available
            }
        }

        $this->io->progressFinish();
    }

    private function displayCapabilities(): void
    {
        $rows = [];
        foreach ($this->optimizerService->getOptimizers() as $optimizer) {
            $rows[] = [
                $optimizer->getName(),
                $optimizer->isRunnable() ? 'Yes' : 'No',
                implode(', ', $optimizer->getSupportedMimeTypes()),
            ];
        }

        $this->io->table(
            ['Optimizer', 'Runnable', 'Supported mime-types'],
            $rows
        );
    }

    private function createMediaFinder(InputInterface $input, OutputInterface $output): Finder
    {
        $mediaPath = $input->getArgument('path') ?: $this->mediaFilesystemPath;
        $realPath = realpath($mediaPath);

        if (!is_dir($realPath)) {
            throw new \RuntimeException(sprintf('Directory "%s" does not exists.', $mediaPath));
        }

        $this->io->comment(sprintf('<info>Searching for files in:</info> %s', $realPath));

        $finder = new Finder();
        $finder
            ->files()
            ->in($realPath);

        if ($input->getOption('modified')) {
            $finder->date($input->getOption('modified'));
        }

        return $finder;
    }
}
