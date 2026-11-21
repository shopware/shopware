<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\Service;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
#[Package('framework')]
class DemoProductsCreator implements EventSubscriberInterface
{
    public const CREATED_FLAG = 'core.demo.productsCreated';

    public function __construct(
        private readonly string $projectDir,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UpdatePostFinishEvent::class => 'onUpdateFinished',
        ];
    }

    public function onUpdateFinished(): void
    {
        if ($this->systemConfigService->getBool(self::CREATED_FLAG)) {
            return;
        }

        self::create($this->projectDir, new ConsoleOutput());
    }

    public static function create(string $projectDir, OutputInterface $output): void
    {
        $console = $projectDir . '/bin/console';
        $steps = [
            [\PHP_BINARY, $console, 'framework:demodata', '--reset-defaults', '--products=500', '--categories=10', '--manufacturers=10', '--properties=10', '--media=100'],
            [\PHP_BINARY, $console, 'dal:refresh:index'],
            [\PHP_BINARY, $console, 'system:config:set', self::CREATED_FLAG, 'true'],
        ];

        foreach ($steps as $step) {
            $process = new Process($step, $projectDir, ['APP_ENV' => 'prod'], null, null);
            $process->run(static function (string $type, string $buffer) use ($output): void {
                $output->write($buffer);
            });

            if (!$process->isSuccessful()) {
                $output->writeln('<error>Creating the demo products failed.</error>');

                return;
            }
        }
    }
}
