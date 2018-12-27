<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Commands;

use Shopware\Core\Content\Media\Exception\FileTypeNotSupportedException;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GenerateThumbnailsCommand extends Command
{
    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * @var ThumbnailService
     */
    private $thumbnailService;

    /**
     * @var EntityRepository
     */
    private $mediaRepository;

    /**
     * @var int int
     */
    private $generatedCounter;

    /**
     * @var int int
     */
    private $skippedCounter;

    /**
     * @var int
     */
    private $batchSize;

    /**
     * @var Filter | null
     */
    private $folderFilter;

    /**
     * @var RepositoryInterface
     */
    private $mediaFolderRepository;

    public function __construct(
        ThumbnailService $thumbnailService,
        EntityRepository $mediaRepository,
        RepositoryInterface $mediaFolderRepository
    ) {
        parent::__construct();

        $this->thumbnailService = $thumbnailService;
        $this->mediaRepository = $mediaRepository;
        $this->mediaFolderRepository = $mediaFolderRepository;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('media:generate-thumbnails')
            ->setDescription('Generates the thumbnails for media entities')
            ->addOption(
                'batch-size',
                'b',
                InputOption::VALUE_REQUIRED,
                'Maximum number of entities to create thumbnails',
                '100'
            )
            ->addOption(
                'folder-name',
                null,
                InputOption::VALUE_REQUIRED,
                'An optional folder name to create thumbnails'
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();

        $this->initializeCommand($input, $context);

        $this->io->comment('Starting to generate Thumbnails. This may take some time...');
        $this->io->progressStart($this->getMediaCount($context));

        $this->generateThumbnails($context);

        $this->io->progressFinish();
        $this->io->table(
            ['Action', 'Number of Media Entities'],
            [
                ['Generated', $this->generatedCounter],
                ['Skipped', $this->skippedCounter],
            ]
        );
    }

    private function initializeCommand(InputInterface $input, Context $context)
    {
        $this->generatedCounter = 0;
        $this->skippedCounter = 0;

        $this->batchSize = $this->getBatchSizeFromInput($input);
        $this->folderFilter = $this->getFolderIdsFromInput($input, $context);
    }

    private function getBatchSizeFromInput(InputInterface $input): int
    {
        $rawInput = $input->getOption('batch-size');
        if (!is_numeric($rawInput)) {
            throw new \UnexpectedValueException('Batch size must be numeric');
        }

        return (int) $rawInput;
    }

    private function getFolderIdsFromInput(InputInterface $input, Context $context)
    {
        $rawInput = $input->getOption('folder-name');
        if (!$rawInput) {
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

    private function getMediaCount(Context $context): int
    {
        $criteria = new Criteria();

        if ($this->folderFilter) {
            $criteria->addFilter($this->folderFilter);
        }

        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $criteria->setLimit(0);
        $result = $this->mediaRepository->search($criteria, $context);

        return $result->getTotal();
    }

    private function generateThumbnails($context): void
    {
        $criteria = $this->createCriteria();

        do {
            $result = $this->mediaRepository->search($criteria, $context);
            foreach ($result->getEntities() as $media) {
                $this->generateThumbnail($context, $media);
            }
            $this->io->progressAdvance($result->count());
            $criteria->setOffset($criteria->getOffset() + $this->batchSize);
        } while ($result->getTotal() > $this->batchSize);
    }

    private function generateThumbnail(Context $context, MediaEntity $media): void
    {
        try {
            $this->thumbnailService->updateThumbnails($media, $context);
            ++$this->generatedCounter;
        } catch (FileTypeNotSupportedException $e) {
            ++$this->skippedCounter;
        }
    }

    private function createCriteria(): Criteria
    {
        $criteria = new Criteria();
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NEXT_PAGES);
        $criteria->setLimit($this->batchSize);

        if ($this->folderFilter) {
            $criteria->addFilter($this->folderFilter);
        }

        $criteria->addAssociation('media.mediaFolder');

        return $criteria;
    }
}
