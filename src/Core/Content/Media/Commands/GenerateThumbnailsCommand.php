<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Commands;

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
     * @var int
     */
    private $limit;

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
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Maximum number of entities to create thumbnails',
                false
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

        $this->io->comment(sprintf('Generating Thumbnails for a maximum of %d files. This may take some time...', $this->limit));
        $this->io->progressStart($this->limit);

        $result = $this->generateThumbnails($context);

        $this->io->progressFinish();
        $this->io->table(
            ['Action', 'Number of Media Entities'],
            [
                ['Generated', $result['generated']],
                ['Skipped', $result['skipped']],
            ]
        );
    }

    private function initializeCommand(InputInterface $input, Context $context)
    {
        $this->folderFilter = $this->getFolderIdsFromInput($input, $context);
        $this->limit = $this->getLimitSizeFromInput($input, $context);
    }

    private function getLimitSizeFromInput(InputInterface $input, Context $context): int
    {
        $rawInput = $input->getOption('limit');

        if ($rawInput === false) {
            return $this->getAvailableMediaEntitiesCount($context);
        }

        if (!is_numeric($rawInput)) {
            throw new \UnexpectedValueException('Limit size must be numeric');
        }

        return (int) $rawInput;
    }

    private function getAvailableMediaEntitiesCount(Context $context): int
    {
        $criteria = $this->createCriteria();
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $searchResult = $this->mediaRepository->search($criteria, $context);

        return $searchResult->getTotal();
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

    private function generateThumbnails($context): array
    {
        $criteria = $this->createCriteria();
        $criteria->setLimit(min($this->limit, 50));

        $generated = 0;
        $skipped = 0;

        do {
            $result = $this->mediaRepository->search($criteria, $context);
            foreach ($result->getEntities() as $media) {
                if ($this->thumbnailService->updateThumbnails($media, $context) > 0) {
                    ++$generated;
                } else {
                    ++$skipped;
                }
            }
            $this->io->progressAdvance($result->count());
            $criteria->setOffset($criteria->getOffset() + $criteria->getLimit());
        } while ($result->count() > 0 && $criteria->getOffset() < $this->limit);

        return [
            'generated' => $generated,
            'skipped' => $skipped,
        ];
    }

    private function createCriteria(): Criteria
    {
        $criteria = new Criteria();
        $criteria->setOffset(0);
        $criteria->addFilter(new EqualsFilter('media.mediaFolder.configuration.createThumbnails', true));

        if ($this->folderFilter) {
            $criteria->addFilter($this->folderFilter);
        }

        $criteria->addAssociation('media.mediaFolder');

        return $criteria;
    }
}
