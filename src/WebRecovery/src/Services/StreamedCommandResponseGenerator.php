<?php
declare(strict_types=1);

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;

class StreamedCommandResponseGenerator
{
    /**
     * @param array<string> $params
     * @param callable(Process): void $finish
     */
    public function run(array $params, callable $finish): StreamedResponse
    {
        $process = new Process($params);

        $process->start();

        return new StreamedResponse(function () use ($process, $finish): void {
            foreach ($process->getIterator() as $item) {
                \assert(\is_string($item));
                echo $item;
                flush();
            }

            $finish($process);
        });
    }

    /**
     * @param array<string> $params
     */
    public function runJSON(array $params): StreamedResponse
    {
        return $this->run($params, function (Process $process): void {
            echo json_encode([
                'success' => $process->isSuccessful(),
            ]);
        });
    }
}
