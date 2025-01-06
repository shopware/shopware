<?php
declare(strict_types=1);

namespace Shopware\WebInstaller\Services;

use Composer\Util\Platform;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
#[Package('core')]
class StreamedCommandResponseGenerator
{
    public const DEFAULT_TIMEOUT = 900.0; // 15 minutes

    /**
     * @param array<string> $params
     * @param callable(Process): void $finish
     */
    public function run(array $params, callable $finish): StreamedResponse
    {
        $process = new Process($params);
        $process->setEnv(['COMPOSER_HOME' => sys_get_temp_dir() . '/composer']);

        // Read process timeout from environment or use default value
        $timeout = Platform::getEnv('SHOPWARE_INSTALLER_TIMEOUT');
        if (empty($timeout) || !is_numeric($timeout) || $timeout < 0) {
            $timeout = self::DEFAULT_TIMEOUT;
        } else {
            $timeout = (float) $timeout;
        }
        $process->setTimeout($timeout);

        $process->start();

        return new StreamedResponse(function () use ($process, $finish): void {
            try {
                foreach ($process->getIterator() as $item) {
                    \assert(\is_string($item));
                    echo $item;
                    flush();
                }
            } catch (ProcessTimedOutException $e) {
                echo 'Update timed out after ' . $e->getExceededTimeout() . " second(s)\n";
            }

            $finish($process);
        });
    }

    /**
     * @param array<string> $params
     */
    public function runJSON(array $params, ?callable $finish = null): StreamedResponse
    {
        return $this->run($params, function (Process $process) use ($finish): void {
            if ($finish !== null) {
                $finish($process);
            }

            echo json_encode([
                'success' => $process->isSuccessful(),
            ]);
        });
    }
}
