<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Docs\App;

use Shopware\Core\DevOps\Docs\ArrayWriter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventCollector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;

#[AsCommand(
    name: 'docs:app-system-events',
    description: 'Dump the app events',
)]
#[Package('core')]
/**
 * @package core
 */
class DocsAppEventCommand extends Command
{
    private const EVENT_DOCUMENT_PATH = __DIR__ . '/../../Resources/generated/webhook-events-reference.md';

    private const EVENTS_TEMPLATE = __DIR__ . '/../../Resources/templates/hookable-events-list.md.twig';

    private const EVENT_DESCRIPTIONS = __DIR__ . '/../../Resources/templates/app-system-event-description-permissions.php';

    /**
     * @internal
     */
    public function __construct(
        private readonly BusinessEventCollector $businessEventCollector,
        private readonly HookableEventCollector $hookableEventCollector,
        private readonly Environment $twig
    ) {
        parent::__construct();
    }

    public function render(): string
    {
        $eventsDoc = [];

        $this->collectBusinessEvent($eventsDoc);

        $this->collectEntityWrittenEvent($eventsDoc);

        $originalLoader = $this->twig->getLoader();
        $this->twig->setLoader(new ArrayLoader([
            'hookable-events-list.md.twig' => file_get_contents(self::EVENTS_TEMPLATE),
        ]));

        try {
            return $this->twig->render(
                'hookable-events-list.md.twig',
                ['eventDocs' => $eventsDoc]
            );
        } catch (LoaderError | RuntimeError | SyntaxError $e) {
            throw new \RuntimeException('Can not render Webhook Events', $e->getCode(), $e);
        } finally {
            $this->twig->setLoader($originalLoader);
        }
    }

    public function getListEventPath(): string
    {
        return self::EVENT_DOCUMENT_PATH;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->section('Generates documentation for all events that can be registered as webhook');

        file_put_contents(
            self::EVENT_DOCUMENT_PATH,
            $this->render()
        );

        $io->success('All events were generated successfully');

        $io->note(self::EVENT_DOCUMENT_PATH);

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
        $context = Context::createDefaultContext();
        $businessEvents = $this->businessEventCollector->collect($context);
        $eventDoc = new ArrayWriter(self::EVENT_DESCRIPTIONS);

        foreach ($businessEvents as $event) {
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
}
