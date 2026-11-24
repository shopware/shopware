<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\FdLeak;

use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * DEBUG: logs every test after which the number of open file descriptors grew.
 *
 * @internal
 */
class FdLeakExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $facade->registerSubscriber(new class implements FinishedSubscriber {
            private int $last = -1;

            public function notify(Finished $event): void
            {
                $fds = @scandir('/proc/self/fd');
                if ($fds === false) {
                    return;
                }
                $pipes = 0;
                foreach ($fds as $fd) {
                    if (str_starts_with((string) @readlink('/proc/self/fd/' . $fd), 'pipe:')) {
                        ++$pipes;
                    }
                }
                $count = \count($fds) - 2;
                if ($count !== $this->last) {
                    file_put_contents('/tmp/mcpbump.fds', \sprintf("%d\t%+d\t%d\t%s\n", $count, $count - max($this->last, 0), $pipes, $event->test()->id()), \FILE_APPEND);
                    $this->last = $count;
                }
            }
        });
    }
}
