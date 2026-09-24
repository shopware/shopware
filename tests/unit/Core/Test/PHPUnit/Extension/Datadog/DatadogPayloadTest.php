<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\PHPUnit\Extension\Datadog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\Extension\Datadog\DatadogPayload;
use Shopware\Core\Test\TestEnvironment;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DatadogPayload::class)]
class DatadogPayloadTest extends TestCase
{
    public function testSerializeOutsideACiPipeline(): void
    {
        TestEnvironment::set(['CI_PROJECT_URL' => null, 'CI_JOB_ID' => null, 'CI_BUILD_ID' => null]);

        $payload = new DatadogPayload('phpunit', 'phpunit,test:failed', 'message', 'PHPUnit', 'fakeFile', 1.5);

        static::assertSame([
            'ddsource' => 'phpunit',
            'ddtags' => 'phpunit,test:failed',
            'message' => 'message',
            'service' => 'PHPUnit',
            'test-description' => 'fakeFile',
            'test-duration' => 1.5,
            'test-build' => 'unavailable',
        ], $payload->serialize());
    }

    public function testSerializeLinksTheCiBuild(): void
    {
        TestEnvironment::set(['CI_PROJECT_URL' => 'https://ci.example/project', 'CI_JOB_ID' => '42']);

        $payload = new DatadogPayload('phpunit', 'phpunit,test:slow', 'message', 'PHPUnit');

        $serialized = $payload->serialize();

        static::assertSame('https://ci.example/project/builds/42', $serialized['test-build']);
        static::assertNull($serialized['test-description']);
        static::assertNull($serialized['test-duration']);
    }
}
