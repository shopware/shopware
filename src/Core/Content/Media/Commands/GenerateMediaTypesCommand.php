<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Commands;

use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\TypeDetector\TypeDetector;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GenerateMediaTypesCommand extends Command
{
    protected static $defaultName = 'media:generate-media-types';

    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * @var TypeDetector
     */
    private $typeDetector;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var int
     */
    private $batchSize;

    public function __construct(TypeDetector $typeDetector, EntityRepositoryInterface $mediaRepository)
    {
        parent::__construct();

        $this->typeDetector = $typeDetector;
        $this->mediaRepository = $mediaRepository;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Generates the media type for all media entities')
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Batch Size')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new ShopwareStyle($input, $output);

        $context = Context::createDefaultContext();
        $this->batchSize = $this->validateBatchSize($input);

        $this->io->comment('Starting to generate MediaTypes. This may take some time...');
        $this->io->progressStart($this->getMediaCount($context));

        $this->detectMediaTypes($context);

        $this->io->progressFinish();

        return self::SUCCESS;
    }

    private function validateBatchSize(InputInterface $input): int
    {
        $batchSize = $input->getOption('batch-size');
        if ($batchSize === null) {
            return 100;
        }

        if (!is_numeric($batchSize)) {
            throw new \RuntimeException('BatchSize is not numeric');
        }

        return (int) $batchSize;
    }

    private function getMediaCount(Context $context): int
    {
        $criteria = new Criteria();
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $criteria->setLimit(1);
        $result = $this->mediaRepository->search($criteria, $context);

        return $result->getTotal();
    }

    private function detectMediaTypes(Context $context): void
    {
        $criteria = $this->createCriteria();

        do {
            $result = $this->mediaRepository->search($criteria, $context);
            foreach ($result->getEntities() as $media) {
                $this->detectMediaType($context, $media);
            }
            $this->io->progressAdvance($result->count());
            $criteria->setOffset($criteria->getOffset() + $this->batchSize);
        } while ($result->getTotal() > $this->batchSize);
    }

    private function detectMediaType(Context $context, MediaEntity $media): void
    {
        if (!$media->hasFile()) {
            return;
        }

        $file = new MediaFile(
            $media->getUrl(),
            $media->getMimeType(),
            $media->getFileExtension(),
            $media->getFileSize()
        );

        $type = $this->typeDetector->detect($file);
        $changeSet = ['id' => $media->getId(), 'mediaTypeRaw' => serialize($type)];

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($changeSet): void {
            $this->mediaRepository->upsert([$changeSet], $context);
        });
    }

    private function createCriteria(): Criteria
    {
        $criteria = new Criteria();
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NEXT_PAGES);
        $criteria->setLimit($this->batchSize);

        return $criteria;
    }
}
