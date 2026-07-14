<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Module;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Feature\TranslatedString;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Module\MainModule;
use Shopware\Core\Framework\App\Module\Module;
use Shopware\Core\Framework\App\Module\ModuleConfig;
use Shopware\Core\Framework\App\Module\ModuleFeatureDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * @internal
 */
#[CoversClass(ModuleFeatureDefinition::class)]
#[Package('framework')]
class ModuleFeatureDefinitionTest extends TestCase
{
    private ModuleFeatureDefinition $definition;

    protected function setUp(): void
    {
        $this->definition = new ModuleFeatureDefinition();
    }

    public function testType(): void
    {
        static::assertSame('module', $this->definition->getType());
        static::assertSame(ModuleConfig::class, $this->definition->getConfigClass());
    }

    public function testFromAppReturnsEmptyWithoutAdminSection(): void
    {
        static::assertSame([], $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__),
            'en-GB',
        ));
    }

    public function testFromAppReadsModulesAndMainModule(): void
    {
        $configs = $this->definition->fromApp(
            Manifest::createFromXmlFile(__DIR__ . '/_fixtures/manifest.xml'),
            new Filesystem(__DIR__),
            'en-GB',
        );

        static::assertCount(1, $configs);

        $config = $configs[0];
        static::assertSame('admin', $config->getName());
        static::assertNotNull($config->mainModule);
        static::assertSame('https://example.com/main', $config->mainModule->source);

        static::assertCount(2, $config->modules);

        $first = $config->modules[0];
        static::assertSame('first-module', $first->name);
        static::assertSame('sw-catalogue', $first->parent);
        static::assertSame('https://example.com/first', $first->source);
        static::assertSame(50, $first->position);
        static::assertSame(['en-GB' => 'First', 'de-DE' => 'Erste'], $first->label->all());

        static::assertSame('second-module', $config->modules[1]->name);
        static::assertNull($config->modules[1]->source);
    }

    public function testPayloadRoundTrip(): void
    {
        $declared = new ModuleConfig(
            [new Module('m', new TranslatedString(['en-GB' => 'M']), 'p', 'https://s', 10)],
            new MainModule('https://main'),
        );

        $hydrated = $this->definition->fromPayload($this->definition->toPayload($declared, null));

        static::assertEquals($declared, $hydrated);
    }

    public function testPayloadRoundTripWithoutMainModule(): void
    {
        $declared = new ModuleConfig([], null);

        $hydrated = $this->definition->fromPayload($this->definition->toPayload($declared, null));

        static::assertEquals($declared, $hydrated);
    }
}
