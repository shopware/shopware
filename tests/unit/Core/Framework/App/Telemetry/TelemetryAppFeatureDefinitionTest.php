<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\App\Exception\AppXmlParsingException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Shopware\Core\Framework\App\Telemetry\AppTelemetryConfig;
use Shopware\Core\Framework\App\Telemetry\AppTelemetryConfigChecker;
use Shopware\Core\Framework\App\Telemetry\TelemetryAppFeatureDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TelemetryAppFeatureDefinition::class)]
class TelemetryAppFeatureDefinitionTest extends TestCase
{
    private const DEFINITIONS = [
        'order.placed.count' => [
            'labels' => [
                'payment_method' => ['add_allowed_values' => ['core_method', 'other']],
            ],
        ],
    ];

    private const MAPPINGS = [
        'entity_group' => ['product' => 'product'],
        'flow_trigger_group' => [],
    ];

    private string $tmpDir;

    private SymfonyFilesystem $fs;

    protected function setUp(): void
    {
        $this->fs = new SymfonyFilesystem();
        $this->tmpDir = sys_get_temp_dir() . '/sw-test-app-' . bin2hex(random_bytes(4));
        $this->fs->mkdir($this->tmpDir . '/Resources/config');
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->tmpDir);
    }

    public function testFromAppReturnsAcceptedEntriesAsOneConfig(): void
    {
        $this->fs->copy(__DIR__ . '/_fixtures/telemetry.xml', $this->tmpDir . '/Resources/config/telemetry.xml');

        $configs = $this->definition()->fromApp($this->createManifest(), new Filesystem($this->tmpDir), 'en-GB');

        static::assertCount(1, $configs);
        static::assertSame(
            [['metric' => 'order.placed.count', 'label' => 'payment_method', 'values' => ['payment_swagpaypal', 'payment_swagpaypal_express']]],
            $configs[0]->labelValues,
        );
        static::assertSame(
            ['entity_group' => ['ce_blog_*' => 'blog', 'swag_blog_post' => 'blog'], 'flow_trigger_group' => ['swag.blog.published' => 'content']],
            $configs[0]->mappings,
        );
    }

    public function testFromAppSkipsInvalidEntriesAndLogsOnce(): void
    {
        $this->fs->dumpFile($this->tmpDir . '/Resources/config/telemetry.xml', <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <telemetry>
                <label-values metric="unknown.metric" label="payment_method">
                    <value>v</value>
                </label-values>
            </telemetry>
            XML);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $configs = $this->definition($logger)->fromApp($this->createManifest(), new Filesystem($this->tmpDir), 'en-GB');

        static::assertSame([], $configs);
    }

    public function testFromAppFailsOnUnparsableFile(): void
    {
        $this->fs->dumpFile($this->tmpDir . '/Resources/config/telemetry.xml', 'not xml at all');

        $this->expectException(AppXmlParsingException::class);

        $this->definition()->fromApp($this->createManifest(), new Filesystem($this->tmpDir), 'en-GB');
    }

    public function testFromAppWithoutFileReturnsNothing(): void
    {
        $configs = $this->definition()->fromApp($this->createManifest(), new Filesystem($this->tmpDir), 'en-GB');

        static::assertSame([], $configs);
    }

    public function testPayloadRoundTrip(): void
    {
        $definition = $this->definition();
        $config = new AppTelemetryConfig(
            labelValues: [['metric' => 'order.placed.count', 'label' => 'payment_method', 'values' => ['v']]],
            mappings: ['entity_group' => ['swag_*' => 'blog']],
        );

        $restored = $definition->fromPayload($definition->toPayload($config, null));

        static::assertSame($config->labelValues, $restored->labelValues);
        static::assertSame($config->mappings, $restored->mappings);
    }

    public function testDeclarationWinsOverStoredPayload(): void
    {
        $definition = $this->definition();
        $declared = new AppTelemetryConfig(mappings: ['entity_group' => ['swag_*' => 'new']]);
        $stored = new AppTelemetryConfig(mappings: ['entity_group' => ['swag_*' => 'shop_edited']]);

        static::assertSame($declared->toArray(), $definition->toPayload($declared, $stored));
    }

    private function definition(?LoggerInterface $logger = null): TelemetryAppFeatureDefinition
    {
        return new TelemetryAppFeatureDefinition(
            new AppTelemetryConfigChecker(self::DEFINITIONS, self::MAPPINGS),
            $logger ?? new NullLogger(),
        );
    }

    private function createManifest(): Manifest
    {
        $metadata = static::createStub(Metadata::class);
        $metadata->method('getName')->willReturn('TestApp');

        $manifest = static::createStub(Manifest::class);
        $manifest->method('getMetadata')->willReturn($metadata);

        return $manifest;
    }
}
