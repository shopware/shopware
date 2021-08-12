<?php declare(strict_types=1);

namespace Shopware\Docs\Command\App;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventCollector;
use Shopware\Docs\Inspection\ArrayWriter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;

class DocsAppEventCommand extends Command
{
    protected static $defaultName = 'docs:app-system-events';

    private string $listEventPath = __DIR__ . '/../../Resources/current/47-app-system-guide/webhook-events-reference.md';

    private string $templateEvents = __DIR__ . '/../../Resources/hookableEventsList.md.twig';

    private string $eventsDescPath = __DIR__ . '/../../Resources/app-system-event-description-permissions.php';

    private BusinessEventCollector $businessEventCollector;

    private HookableEventCollector $hookableEventCollector;

    private Environment $twig;

    public function __construct(
        BusinessEventCollector $businessEventCollector,
        HookableEventCollector $hookableEventCollector,
        Environment $twig
    ) {
        parent::__construct();
        $this->businessEventCollector = $businessEventCollector;
        $this->hookableEventCollector = $hookableEventCollector;
        $this->twig = $twig;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->section('Generates documentation for all events that can be registered as webhook');

        file_put_contents(
            $this->listEventPath,
            $this->render()
        );

        $io->success('All events was generated successfully');

        $io->note($this->listEventPath);

        return self::SUCCESS;
    }

    public function render(): string
    {
        $eventsDoc = [];

        $this->collectBusinessEvent($eventsDoc);

        $this->collectEntityWrittenEvent($eventsDoc);

        $this->twig->setLoader(new ArrayLoader([
            'hookableEventsList.md.twig' => file_get_contents($this->templateEvents),
        ]));

        try {
            return $this->twig->render(
                'hookableEventsList.md.twig',
                ['eventDocs' => $eventsDoc]
            );
        } catch (LoaderError | RuntimeError | SyntaxError $e) {
            throw new \RuntimeException('Can not render Webhook Events');
        }
    }

    public function getListEventPath(): string
    {
        return $this->listEventPath;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Generates documentation for all events that can be registered as webhook');
    }

    private function collectBusinessEvent(array &$eventsDoc): void
    {
        $context = Context::createDefaultContext();
        $businessEvents = $this->businessEventCollector->collect($context);
        $eventDoc = new ArrayWriter($this->eventsDescPath);

        foreach ($businessEvents as $event) {
            $eventDoc->ensure($event->getName());

            $eventsDoc[] = HookableEventDoc::fromBusinessEvent(
                $event,
                $this->hookableEventCollector->getPrivilegesFromBusinessEventDefinition($event),
                $eventDoc->get($event->getName())
            );
        }
    }

    private function collectEntityWrittenEvent(array &$eventsDoc): void
    {
        $entityWrittenEvents = $this->hookableEventCollector->getEntityWrittenEventNamesWithPrivileges();

        foreach ($entityWrittenEvents as $event => $permission) {
            $eventsDoc[]
                = HookableEventDoc::fromEntityWrittenEvent($event, $permission['privileges']);
        }
    }
}
