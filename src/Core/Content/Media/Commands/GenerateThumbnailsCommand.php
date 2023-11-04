<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Commands;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Message\UpdateThumbnailsMessage;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'media:generate-thumbnails',
    description: 'Generates thumbnails for all media files',
)]
#[Package('content')]
class GenerateThumbnailsCommand extends Command
{
    private ShopwareStyle $io;

    private ?int $batchSize = null;

    private ?Filter $folderFilter = null;

    private bool $isAsync;

    private bool $isStrict;

    /**
     * @internal
     */
    public function __construct(
        private readonly ThumbnailService $thumbnailService,
        private readonly EntityRepository $mediaRepository,
        private readonly EntityRepository $mediaFolderRepository,
        private readonly MessageBusInterface $messageBus
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Number of entities per iteration', '50')
            ->addOption(
                'folder-name',
                null,
                InputOption::VALUE_REQUIRED,
                'An optional folder name to create thumbnails'
            )
            ->addOption(
                'async',
                'a',
                InputOption::VALUE_NONE,
                'Queue up batch jobs instead of generating thumbnails directly'
            )
            ->addOption(
                'strict',
                's',
                InputOption::VALUE_NONE,
                'Additionally checks that physical files for existing thumbnails are present'
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new ShopwareStyle($input, $output);
        $context = Context::createDefaultContext();

        $this->initializeCommand($input, $context);

        $mediaIterator = new RepositoryIterator($this->mediaRepository, $context, $this->createCriteria());

        if (!$this->isAsync) {
            $this->generateSynchronous($mediaIterator, $context);
        } else {
            $this->generateAsynchronous($mediaIterator, $context);
        }

        return self::SUCCESS;
    }

    private function initializeCommand(InputInterface $input, Context $context): void
    {
        $this->folderFilter = $this->getFolderFilterFromInput($input, $context);
        $this->batchSize = $this->getBatchSizeFromInput($input);
        $this->isAsync = $input->getOption('async');
        $this->isStrict = $input->getOption('strict');
    }

    private function getBatchSizeFromInput(InputInterface $input): int
    {
        $rawInput = $input->getOption('batch-size');

        if (!is_numeric($rawInput)) {
            throw new \UnexpectedValueException('Batch size must be numeric');
        }

        return (int) $rawInput;
    }

    private function getFolderFilterFromInput(InputInterface $input, Context $context): ?EqualsAnyFilter
    {
        $rawInput = $input->getOption('folder-name');
        if (empty($rawInput)) {
            return null;
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('name', $rawInput));

        $searchResult = $this->mediaFolderRepository->search($criteria, $context);

        if ($searchResult->getTotal() === 0) {
            throw new \UnexpectedValueException(
                sprintf(
                    'Could not find a folder with the name: "%s"',
                    $rawInput
                )
            );
        }

        return new EqualsAnyFilter('mediaFolderId', $searchResult->getIds());
    }

    /**
     * @return array<string, int|array<array<string>>>
     */
    private function generateThumbnails(RepositoryIterator $iterator, Context $context): array
    {
        $generated = 0;
        $skipped = 0;
        $errored = 0;
        $errors = [];

        while (($result = $iterator->fetch()) !== null) {
            /** @var MediaEntity $media */
            foreach ($result->getEntities() as $media) {
                try {
                    if ($this->thumbnailService->updateThumbnails($media, $context, $this->isStrict) > 0) {
                        ++$generated;
                    } else {
                        ++$skipped;
                    }
                } catch (\Throwable $e) {
                    ++$errored;
                    $errors[] = [sprintf('Cannot process file %s (id: %s) due error: %s', $media->getFileName(), $media->getId(), $e->getMessage())];
                }
            }
            $this->io->progressAdvance($result->count());
        }

        return [
            'generated' => $generated,
            'skipped' => $skipped,
            'errored' => $errored,
            'errors' => $errors,
        ];
    }

    private function createCriteria(): Criteria
    {
        $criteria = new Criteria();
        $criteria->setOffset(0);
        $criteria->setLimit($this->batchSize);
        $criteria->addFilter(new EqualsFilter('media.mediaFolder.configuration.createThumbnails', true));
        $criteria->addAssociation('thumbnails');
        $criteria->addAssociation('mediaFolder.configuration.mediaThumbnailSizes');

        if ($this->folderFilter) {
            $criteria->addFilter($this->folderFilter);
        }

        return $criteria;
    }

    private function generateSynchronous(RepositoryIterator $mediaIterator, Context $context): void
    {
        $totalMediaCount = $mediaIterator->getTotal();
        $this->io->comment(sprintf('Generating Thumbnails for %d files. This may take some time...', $totalMediaCount));
        $this->io->progressStart($totalMediaCount);

        $result = $this->generateThumbnails($mediaIterator, $context);

        $this->io->progressFinish();
        $this->io->table(
            ['Action', 'Number of Media Entities'],
            [
                ['Generated', $result['generated']],
                ['Skipped', $result['skipped']],
                ['Errors', $result['errored']],
            ]
        );

        if (is_countable($result['errors']) ? \count($result['errors']) : 0) {
            if ($this->io->isVerbose()) {
                /** @var array<array<string>> $errors */
                $errors = $result['errors'];
                $this->io->table(
                    ['Error messages'],
                    $errors
                );
            } else {
                $this->io->warning(\sprintf('Thumbnail generation for %d file(s) failed. Use -v to show the files', is_countable($result['errors']) ? \count($result['errors']) : 0));
            }
        }
    }

    private function generateAsynchronous(RepositoryIterator $mediaIterator, Context $context): void
    {
        $batchCount = 0;
        $this->io->comment('Generating batch jobs...');
        while (($result = $mediaIterator->fetch()) !== null) {
            $msg = new UpdateThumbnailsMessage();
            $msg->setIsStrict($this->isStrict);
            $msg->setMediaIds($result->getEntities()->getIds());

            if (Feature::isActive('v6.6.0.0')) {
                $msg->setContext($context);
            } else {
                $msg->withContext($context);
            }

            $this->messageBus->dispatch($msg);
            ++$batchCount;
        }
        $this->io->success(sprintf('Generated %d Batch jobs!', $batchCount));
    }
}
