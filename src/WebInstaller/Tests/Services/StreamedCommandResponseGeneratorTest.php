<?php
declare(strict_types=1);

namespace Shopware\WebInstaller\Tests\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\WebInstaller\Services\StreamedCommandResponseGenerator;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
#[CoversClass(StreamedCommandResponseGenerator::class)]
class StreamedCommandResponseGeneratorTest extends TestCase
{
    public function testRun(): void
    {
        $generator = new StreamedCommandResponseGenerator();

        $response = $generator->run(['echo', 'foo'], function (Process $process): void {
            static::assertTrue($process->isSuccessful());
        });

        ob_start();
        $response->sendContent();

        $content = ob_get_clean();

        static::assertSame('foo', trim((string) $content));
    }

    public function testRunJSON(): void
    {
        $generator = new StreamedCommandResponseGenerator();

        $response = $generator->runJSON(['echo', 'foo']);

        ob_start();
        $response->sendContent();

        $content = ob_get_clean();

        static::assertSame('foo' . \PHP_EOL . '{"success":true}', $content);
    }
}
