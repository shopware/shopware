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
    public function testCreateFromXmlFileTrimsAndDeduplicatesKeys(): void
    {
        $config = AppTelemetryConfig::createFromXmlFile(__DIR__ . '/_fixtures/telemetry.xml');

        static::assertSame([
            'entity_group' => ['ce_blog_*', 'swag_blog_post'],
            'flow_trigger_group' => ['swag.blog.published'],
        ], $config->mappings);
    }

    public function testCreateFromXmlFileRejectsSchemaViolation(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'sw-telemetry-');
        static::assertIsString($file);
        file_put_contents($file, '<telemetry><mapping><key>k</key></mapping></telemetry>');

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

        static::assertSame($config->mappings, $restored->mappings);
        static::assertSame(AppTelemetryConfig::NAME, $restored->getName());
        static::assertFalse($restored->isEmpty());
        static::assertTrue((new AppTelemetryConfig())->isEmpty());
    }
}
