<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Commands;

use Shopware\Core\Content\Media\Exception\FileTypeNotSupportedException;
use Shopware\Core\Content\Media\MediaStruct;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GenerateThumbnailsCommand extends Command
{
    /** @var SymfonyStyle */
    private $io;

    /** @var ThumbnailService */
    private $thumbnailService;

    /** @var EntityRepository */
    private $mediaRepository;

    /** @var int int */
    private $generatedCounter;

    /** @var int int */
    private $skippedCounter;

    /** @var int */
    private $batchSize;

    public function __construct(ThumbnailService $thumbnailService, EntityRepository $mediaRepository)
    {
        parent::__construct();

        $this->thumbnailService = $thumbnailService;
        $this->mediaRepository = $mediaRepository;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('media:generate-thumbnails')
            ->setDescription('generates the thumbnails for all media entities')
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Batch Size')
            ->addOption('catalog-id', 'c', InputOption::VALUE_REQUIRED, 'Catalog Id')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->generatedCounter = 0;
        $this->skippedCounter = 0;

        $this->io = new SymfonyStyle($input, $output);

        $tenantId = $this->validateTenantId($input);
        $context = Context::createDefaultContext($tenantId);
        $context = $context->createWithCatalogIds([$this->validateCatalogId($input)]);
        $this->batchSize = $this->validateBatchSize($input);

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

    private function validateTenantId(InputInterface $input): string
    {
        $tenantId = $input->getOption('tenant-id');

        if (!Uuid::isValid($tenantId)) {
            throw new \Exception('Invalid uuid provided for tenantId');
        }

        return $tenantId;
    }

    private function validateCatalogId(InputInterface $input): string
    {
        $catalogId = $input->getOption('catalog-id');
        if (!$catalogId) {
            return Defaults::CATALOG;
        }

        if (!Uuid::isValid($catalogId)) {
            throw new \Exception('Invalid uuid provided for catalogId');
        }

        return $catalogId;
    }

    private function validateBatchSize(InputInterface $input): int
    {
        $batchSize = (int) $input->getOption('batch-size');
        if (!$batchSize) {
            return 100;
        }

        if (!is_numeric($batchSize)) {
            throw new \Exception('BatchSize is not numeric');
        }

        return $batchSize;
    }

    private function getMediaCount(Context $context): int
    {
        $criteria = new Criteria();
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

    private function generateThumbnail(Context $context, MediaStruct $media): void
    {
        try {
            $this->thumbnailService->deleteThumbnails($media, $context);
            $this->thumbnailService->generateThumbnails($media, $context);

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

        return $criteria;
    }
}
