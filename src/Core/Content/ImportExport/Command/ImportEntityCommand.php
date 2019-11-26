<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Command;

use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Iterator\ProgressBarIterator;
use Shopware\Core\Content\ImportExport\Service\InitiationService;
use Shopware\Core\Content\ImportExport\Service\ProcessingService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ImportEntityCommand extends Command
{
    protected static $defaultName = 'import:entity';

    /**
     * @var InitiationService
     */
    private $initiationService;

    /**
     * @var ProcessingService
     */
    private $processingService;

    /**
     * @var EntityRepositoryInterface
     */
    private $profileRepository;

    public function __construct(InitiationService $initiationService, ProcessingService $processingService, EntityRepositoryInterface $profileRepository)
    {
        parent::__construct();
        $this->initiationService = $initiationService;
        $this->processingService = $processingService;
        $this->profileRepository = $profileRepository;
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

        try {
            $expireDate = new \DateTimeImmutable($input->getArgument('expireDate'));
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(sprintf(
                '"%s" is not a valid date. Please use format Y-m-d',
                $input->getArgument('expireDate')
            ));
        }

        $log = $this->initiationService->initiate(
            $context,
            'import',
            $profile,
            $expireDate,
            $filePath,
            basename($filePath)
        );

        $startTime = time();

        $recordIterator = $this->processingService->createRecordIterator($context, $log);

        $outer = new ProgressBarIterator($recordIterator, $io->createProgressBar($log->getRecords()));

        $io->title(sprintf('Starting import of %d records', $log->getRecords()));

        $processed = $this->processingService->process($context, $log, $outer);

        $elapsed = time() - $startTime;
        $io->newLine(2);
        $io->success(sprintf('Successfully imported %d records in %d seconds', $processed, $elapsed));

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
