<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Stub;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;

/**
 * @internal
 */
#[Package('framework')]
class ScheduledTaskGenerator implements ScaffoldingGenerator
{
    use AddScaffoldConfigDefaultBehaviour;
    use HasCommandOption;

    public const OPTION_NAME = 'create-scheduled-task';
    private const OPTION_TITLE = 'Scheduled Task';
    private const OPTION_DESCRIPTION = 'Create an example scheduled task';
    private const OPTION_DESCRIPTION_LONG = 'Quite often one might want to run any type of code on a regular basis, e.g. to clean up very old entries every once in a while, automatically. Usually known as "Cronjobs", Shopware 6 supports a ScheduledTask for this.';
    private const CLI_QUESTION = 'Do you want to create an example scheduled task?';

    private string $servicesPhpEntry = <<<'EOL'

    $services->set(\{{ namespace }}\ScheduledTask\ExampleTask::class)
        ->tag('shopware.scheduled.task');
    $services->set(\{{ namespace }}\ScheduledTask\ExampleTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
        ])
        ->tag('messenger.message_handler');

EOL;

    public function generateStubs(
        PluginScaffoldConfiguration $configuration,
        StubCollection $stubCollection
    ): void {
        if (!$configuration->hasOption(self::OPTION_NAME) || !$configuration->getOption(self::OPTION_NAME)) {
            return;
        }

        $stubCollection->add($this->createScheduledTask($configuration));
        $stubCollection->add($this->createScheduledTaskHandler($configuration));

        $stubCollection->append(
            'src/Resources/config/services.php',
            str_replace(
                '{{ namespace }}',
                $configuration->namespace,
                $this->servicesPhpEntry
            )
        );
    }

    private function createScheduledTask(PluginScaffoldConfiguration $configuration): Stub
    {
        return Stub::template(
            'src/ScheduledTask/ExampleTask.php',
            self::STUB_DIRECTORY . '/scheduled-task.stub',
            [
                'namespace' => $configuration->namespace,
            ]
        );
    }

    private function createScheduledTaskHandler(PluginScaffoldConfiguration $configuration): Stub
    {
        return Stub::template(
            'src/ScheduledTask/ExampleTaskHandler.php',
            self::STUB_DIRECTORY . '/scheduled-task-handler.stub',
            [
                'namespace' => $configuration->namespace,
            ]
        );
    }
}
