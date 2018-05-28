<?php declare(strict_types=1);

namespace Shopware\Framework\Command;

use Shopware\Framework\Plugin\PluginCollection;
use Shopware\Framework\Struct\Uuid;
use Shopware\Framework\Event\ImportAdvanceEvent;
use Shopware\Framework\Event\ImportFinishEvent;
use Shopware\Framework\Event\ImportStartEvent;
use Shopware\Framework\Translation\ImportService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TranslationImportCommand extends ContainerAwareCommand implements EventSubscriberInterface
{
    /**
     * @var ImportService
     */
    private $importService;

    /**
     * @var SymfonyStyle
     */
    private $io;

    public function __construct(ImportService $importService)
    {
        parent::__construct();

        $this->importService = $importService;
    }

    public static function getSubscribedEvents()
    {
        return [
            ImportStartEvent::EVENT_NAME => 'onStart',
            ImportFinishEvent::EVENT_NAME => 'onFinish',
            ImportAdvanceEvent::EVENT_NAME => 'onAdvance',
        ];
    }

    public function onStart(ImportStartEvent $event)
    {
        $this->io->comment('Importing translation files.');
        $this->io->progressStart($event->getCount());

        if ($event->isTruncateBeforeRun()) {
            $this->io->warning('Truncating translation table.');
        }
    }

    public function onAdvance()
    {
        $this->io->progressAdvance();
    }

    public function onFinish()
    {
        $this->io->progressFinish();
        $this->io->success('Translations imported successfully.');
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('translation:import')
            ->setDefinition([
                new InputOption('with-plugins', null, InputOption::VALUE_NONE, 'Search through plugin directories for translation files.'),
                new InputOption('force', 'f', InputOption::VALUE_NONE, 'Truncate table before importing the translations.'),
                new InputOption('tenant-id', 't', InputOption::VALUE_REQUIRED, 'Tenant id'),
            ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        $tenantId = $input->getOption('tenant-id');

        if (!$tenantId) {
            throw new \Exception('No tenant id provided');
        }
        if (!Uuid::isValid($tenantId)) {
            throw new \Exception('Invalid uuid provided');
        }

        $folders = [__DIR__ . '/../Resources/translations'];

        if ($input->getOption('with-plugins')) {
            foreach ($this->getContainer()->get(PluginCollection::class)->getActivePlugins() as $plugin) {
                $translationPath = $plugin->getPath() . '/Resources/translations';
                if (!file_exists($translationPath)) {
                    continue;
                }

                $folders[] = $translationPath;
            }
        }

        $truncate = (bool) $input->getOption('force');

        $this->importService->import($folders, $truncate, $tenantId);
    }
}
