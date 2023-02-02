<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;
use function class_exists;
use function curl_close;
use function curl_exec;
use function curl_init;
use function curl_setopt;
use function json_encode;
use function register_shutdown_function;
use function sprintf;
use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const JSON_THROW_ON_ERROR;

/**
 * @internal
 */
class DatadogListener implements TestListener
{
    use TestListenerDefaultImplementation;
    private const THRESHOLD = 2;

    /**
     * @var array<class-string, float>
     */
    private array $testRunTime = [];

    /**
     * @var array<array<mixed>>
     */
    private array $failedTests = [];

    private bool $isShutdownHandlerRegistered = false;

    public function endTest(Test $test, float $time): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        if (!$test instanceof TestCase) {
            return;
        }

        $key = $test::class;

        if (!class_exists($key)) {
            return;
        }

        if (!isset($this->testRunTime[$key])) {
            $this->testRunTime[$key] = 0;
        }

        $this->testRunTime[$key] += $time;

        $this->register();
    }

    public function addFailure(Test $test, AssertionFailedError $e, float $time): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        if (!$test instanceof TestCase) {
            return;
        }

        $this->register();

        $this->failedTests[] = [
            'ddsource' => 'phpunit',
            'ddtags' => 'phpunit,test:failed',
            'message' => sprintf('Test %s:%s failed with error: %s', $test::class, $test->getName(), $e->getMessage()),
            'service' => 'PHPUnit',
            'test-description' => $test::class,
            'test-duration' => $time,
        ];
    }

    public function send(): void
    {
        $this->sendDuration();
        $this->sendLogs($this->failedTests);
    }

    private function register(): void
    {
        if (!$this->isShutdownHandlerRegistered) {
            $this->isShutdownHandlerRegistered = true;
            register_shutdown_function(function (): void {
                $this->send();
            });
        }
    }

    private function sendDuration(): void
    {
        $data = [];

        foreach ($this->testRunTime as $name => $time) {
            if ($time < self::THRESHOLD) {
                continue;
            }

            $payload = [
                'ddsource' => 'phpunit',
                'ddtags' => 'phpunit,test:slow',
                'message' => 'Slow test ' . $name,
                'service' => 'PHPUnit',
                'test-description' => $name,
                'test-duration' => $time,
            ];

            $data[] = $payload;
        }

        $this->sendLogs($data);
    }

    /**
     * @param array<mixed> $logs
     */
    private function sendLogs(array $logs): void
    {
        if ($logs === []) {
            return;
        }

        $ch = curl_init('https://http-intake.logs.datadoghq.eu/v1/input');
        \assert($ch instanceof \CurlHandle);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($logs, JSON_THROW_ON_ERROR));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'DD-API-KEY: ' . $_SERVER['DATADOG_API_KEY'],
        ]);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }

    private function isEnabled(): bool
    {
        return isset($_SERVER['DATADOG_API_KEY'], $_SERVER['CI_COMMIT_REF_NAME']) && $_SERVER['CI_COMMIT_REF_NAME'] === 'trunk';
    }
}
