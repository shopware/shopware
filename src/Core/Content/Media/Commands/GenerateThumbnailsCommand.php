<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Commands;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Message\UpdateThumbnailsMessage;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

class GenerateThumbnailsCommand extends Command
{
    protected static $defaultName = 'media:generate-thumbnails';

    /**
     * @var ThumbnailService
     */
    private $thumbnailService;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaFolderRepository;

    /**
     * @var MessageBusInterface
     */
    private $messageBus;

    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * @var int
     */
    private $batchSize;

    /**
     * @var Filter|null
     */
    private $folderFilter;

    /**
     * @var bool
     */
    private $isAsync;

    public function __construct(
        ThumbnailService $thumbnailService,
        EntityRepositoryInterface $mediaRepository,
        EntityRepositoryInterface $mediaFolderRepository,
        MessageBusInterface $messageBus
    ) {
        parent::__construct();

        $this->thumbnailService = $thumbnailService;
        $this->mediaRepository = $mediaRepository;
        $this->mediaFolderRepository = $mediaFolderRepository;
        $this->messageBus = $messageBus;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Generates the thumbnails for media entities')
            ->addOption(
                'batch-size',
                'b',
                InputOption::VALUE_REQUIRED,
                'Number of entities per iteration',
                '50'
            )
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
                    if ($this->thumbnailService->updateThumbnails($media, $context) > 0) {
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

        if (\count($result['errors'])) {
            if ($this->io->isVerbose()) {
                $this->io->table(
                    ['Error messages'],
                    $result['errors']
                );
            } else {
                $this->io->warning(\sprintf('Thumbnail generation for %d file(s) failed. Use -v to show the files', \count($result['errors'])));
            }
        }
    }

    private function generateAsynchronous(RepositoryIterator $mediaIterator, Context $context): void
    {
        $batchCount = 0;
        $this->io->comment('Generating batch jobs...');
        while (($result = $mediaIterator->fetch()) !== null) {
            $msg = new UpdateThumbnailsMessage();
            $msg->setMediaIds($result->getEntities()->getIds());
            $msg->withContext($context);

            $this->messageBus->dispatch($msg);
            ++$batchCount;
        }
        $this->io->success(sprintf('Generated %d Batch jobs!', $batchCount));
    }
}
