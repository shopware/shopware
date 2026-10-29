<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Exception\AppXmlParsingException;
use Shopware\Core\Framework\App\Telemetry\AppTelemetryConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppTelemetryConfig::class)]
class AppTelemetryConfigTest extends TestCase
{
    public function testCreateFromXmlFile(): void
    {
        $config = AppTelemetryConfig::createFromXmlFile(__DIR__ . '/_fixtures/telemetry.xml');

        static::assertSame([
            [
                'metric' => 'order.placed.count',
                'label' => 'payment_method',
                'values' => ['payment_swagpaypal', 'payment_swagpaypal_express'],
            ],
        ], $config->labelValues);

        static::assertSame([
            'entity_group' => [
                'ce_blog_*' => 'blog',
                'swag_blog_post' => 'blog',
            ],
            'flow_trigger_group' => [
                'swag.blog.published' => 'content',
            ],
        ], $config->mappings);
    }

    public function testCreateFromXmlFileRejectsInvalidXml(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'sw-telemetry-');
        static::assertIsString($file);
        file_put_contents($file, '<telemetry><label-values metric="m"><value>v</value></label-values></telemetry>');

        try {
            $this->expectException(AppXmlParsingException::class);

            AppTelemetryConfig::createFromXmlFile($file);
        } finally {
            @unlink($file);
        }
    }

    public function testRoundTripThroughArray(): void
    {
        $config = AppTelemetryConfig::createFromXmlFile(__DIR__ . '/_fixtures/telemetry.xml');

        $restored = AppTelemetryConfig::fromArray($config->toArray());

        static::assertSame($config->labelValues, $restored->labelValues);
        static::assertSame($config->mappings, $restored->mappings);
        static::assertFalse($restored->isEmpty());
        static::assertTrue((new AppTelemetryConfig())->isEmpty());
    }
}
