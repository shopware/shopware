<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Command;

use Shopware\Core\Content\ImportExport\ImportExportFactory;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImportEntityCommand extends Command
{
    protected static $defaultName = 'import:entity';

    /**
     * @var ImportExportService
     */
    private $initiationService;

    /**
     * @var EntityRepositoryInterface
     */
    private $profileRepository;

    /**
     * @var ImportExportFactory
     */
    private $importExportFactory;

    public function __construct(ImportExportService $initiationService, EntityRepositoryInterface $profileRepository, ImportExportFactory $importExportFactory)
    {
        parent::__construct();
        $this->initiationService = $initiationService;
        $this->profileRepository = $profileRepository;
        $this->importExportFactory = $importExportFactory;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to import file')
            ->addArgument('expireDate', InputArgument::REQUIRED, 'PHP DateTime compatible string');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();

        $profile = $this->chooseProfile($context, $io);
        $filePath = $input->getArgument('file');

        $expireDateString = $input->getArgument('expireDate');
        if ($expireDateString === null) {
            throw new \InvalidArgumentException(
                sprintf('"%s" is not a valid date. Please use format Y-m-d', $expireDateString)
            );
        }
        if (\is_array($expireDateString)) {
            $expireDateString = implode('', $expireDateString);
        }

        try {
            $expireDate = new \DateTimeImmutable($expireDateString);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(
                sprintf('"%s" is not a valid date. Please use format Y-m-d', $expireDateString)
            );
        }

        $file = new UploadedFile($filePath, basename($filePath), $profile->getFileType());

        $log = $this->initiationService->prepareImport(
            $context,
            $profile->getId(),
            $expireDate,
            $file
        );

        $startTime = time();

        $importExport = $this->importExportFactory->create($log->getId());

        $total = filesize($filePath);
        $progressBar = $io->createProgressBar($total);

        $io->title(sprintf('Starting import of size %d ', $total));

        $records = 0;

        $progress = new Progress($log->getId(), Progress::STATE_PROGRESS, 0);
        do {
            $progress = $importExport->import(Context::createDefaultContext(), $progress->getOffset());
            $progressBar->setProgress($progress->getOffset());
            $records += $progress->getProcessedRecords();
        } while (!$progress->isFinished());

        $elapsed = time() - $startTime;
        $io->newLine(2);
        $io->success(sprintf('Successfully imported %d records in %d seconds', $records, $elapsed));

        return 0;
    }

    private function chooseProfile(Context $context, SymfonyStyle $io): ImportExportProfileEntity
    {
        $result = $this->profileRepository->search(new Criteria(), $context);

        $byName = [];
        foreach ($result->getEntities() as $profile) {
            $byName[$profile->getName()] = $profile;
        }

        $answer = $io->choice('Please choose a profile', array_keys($byName));

        return $byName[$answer];
    }
}
