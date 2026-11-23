<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Docs\App;

use Shopware\Core\DevOps\Docs\ArrayWriter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Authorization\Policy\PolicyRegistry;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventCollector;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventDescriber;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[Package('framework')]
#[AsCommand(
    name: 'docs:app-system-events',
    description: 'Dump the app events',
)]
class DocsAppEventCommand extends Command
{
    private const EVENT_DOCUMENT_PATH = __DIR__ . '/../../Resources/generated/webhook-events-reference.md';

    private const EVENT_JSON_PATH = __DIR__ . '/../../Resources/generated/webhook-events-reference.json';

    private const EVENTS_TEMPLATE = __DIR__ . '/../../Resources/templates/hookable-events-list.md.twig';

    private const EVENT_DESCRIPTIONS = __DIR__ . '/../../Resources/templates/app-system-event-description-permissions.php';

    /**
     * @internal
     *
     * @param iterable<HookableEventDescriber> $hookableEventDescribers
     */
    public function __construct(
        private readonly BusinessEventCollector $businessEventCollector,
        private readonly HookableEventCollector $hookableEventCollector,
        private readonly PolicyRegistry $policies,
        private readonly iterable $hookableEventDescribers,
        private readonly Environment $twig,
        private readonly Filesystem $filesystem
    ) {
        parent::__construct();
    }

    public function render(): string
    {
        $eventsDoc = [];

        $this->collectBusinessEvent($eventsDoc);

        $this->collectEntityWrittenEvent($eventsDoc);

        $this->collectHookables($eventsDoc);

        $originalLoader = $this->twig->getLoader();
        $this->twig->setLoader(new ArrayLoader([
            'hookable-events-list.md.twig' => file_get_contents(self::EVENTS_TEMPLATE),
        ]));

        try {
            return $this->twig->render(
                'hookable-events-list.md.twig',
                ['eventDocs' => $eventsDoc]
            );
        } finally {
            $this->twig->setLoader($originalLoader);
        }
    }

    public function renderJson(): string
    {
        $events = array_filter(
            $this->hookableEventCollector->getHookableEventNamesWithPrivileges(Context::createCLIContext(), null),
            fn (string $eventName) => $this->policies->permitsSubscription($eventName, Subscriber::none()),
            \ARRAY_FILTER_USE_KEY
        );

        return json_encode($events, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR) . \PHP_EOL;
    }

    public function getListEventPath(): string
    {
        return self::EVENT_DOCUMENT_PATH;
    }

    public function getJsonEventPath(): string
    {
        return self::EVENT_JSON_PATH;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->section('Generates documentation for all events that can be registered as webhook');

        $this->filesystem->dumpFile($this->getListEventPath(), $this->render());

        $this->filesystem->dumpFile($this->getJsonEventPath(), $this->renderJson());

        $io->success('All events were generated successfully');

        $io->note([$this->getListEventPath(), $this->getJsonEventPath()]);

        return self::SUCCESS;
    }

    protected function configure(): void
    {
    }

    /**
     * @param list<HookableEventDoc> $eventsDoc
     */
    private function collectBusinessEvent(array &$eventsDoc): void
    {
        $context = Context::createCLIContext();
        $businessEvents = $this->businessEventCollector->collect($context);
        $eventDoc = new ArrayWriter(self::EVENT_DESCRIPTIONS);

        foreach ($businessEvents as $event) {
            // Events no policy permits for a generic subscriber never deliver; don't document them.
            if (!$this->policies->permitsSubscription($event->getName(), Subscriber::none())) {
                continue;
            }

            $eventDoc->ensure($event->getName());

            $eventsDoc[] = HookableEventDoc::fromBusinessEvent(
                $event,
                $this->hookableEventCollector->getPrivilegesFromBusinessEventDefinition($event),
                $eventDoc->get($event->getName())
            );
        }
    }

    /**
     * @param list<HookableEventDoc> $eventsDoc
     */
    private function collectEntityWrittenEvent(array &$eventsDoc): void
    {
        $entityWrittenEvents = $this->hookableEventCollector->getEntityWrittenEventNamesWithPrivileges();

        foreach ($entityWrittenEvents as $event => $permission) {
            $eventsDoc[]
                = HookableEventDoc::fromEntityWrittenEvent($event, $permission['privileges']);
        }
    }

    /**
     * @param list<HookableEventDoc> $eventsDoc
     */
    private function collectHookables(array &$eventsDoc): void
    {
        foreach ($this->hookableEventDescribers as $describer) {
            foreach ($describer->describe() as $eventDescription) {
                if (!$this->policies->permitsSubscription($eventDescription->eventName, Subscriber::none())) {
                    continue;
                }

                $permissions = $eventDescription->privileges ? '`' . implode('` `', $eventDescription->privileges) . '`' : '-';

                $eventsDoc[] = new HookableEventDoc(
                    $eventDescription->eventName,
                    $eventDescription->description,
                    $permissions,
                    null,
                );
            }
        }
    }
}
