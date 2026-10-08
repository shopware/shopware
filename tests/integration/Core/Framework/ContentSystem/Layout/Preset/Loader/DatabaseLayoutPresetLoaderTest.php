<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Layout\Preset\Loader;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\AppContentSystemLayoutPreset\AppContentSystemLayoutPresetCollection;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\LayoutPresetPayloadCompiler;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader\DatabaseLayoutPresetLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Serialization\LayoutPresetSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\ContentSystemLayoutPresetSpecification;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
class DatabaseLayoutPresetLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[TestDox('serves the presets of active apps and leaves out the presets of inactive apps')]
    public function testLoadServesPresetsOfActiveAppsOnly(): void
    {
        $ids = new IdsCollection();
        $this->createApp($ids->get('active-app'), 'AcmeActivePresets', true);
        $this->createApp($ids->get('inactive-app'), 'AcmeInactivePresets', false);
        $this->createPreset($ids->get('active-preset'), $ids->get('active-app'), 'AcmeActivePresets:Hero');
        $this->createPreset($ids->get('inactive-preset'), $ids->get('inactive-app'), 'AcmeInactivePresets:Hero');

        $presets = $this->loader()->load();

        static::assertSame(
            ['AcmeActivePresets:Hero'],
            array_map(static fn (ContentSystemLayoutPresetSpecification $preset): string => $preset->id, $presets),
        );
    }

    private function createApp(string $appId, string $appName, bool $active): void
    {
        $this->appRepository()->create([[
            'id' => $appId,
            'name' => $appName,
            'path' => $appName,
            'version' => '1.0.0',
            'label' => $appName,
            'active' => $active,
            'integration' => ['label' => $appName, 'accessKey' => 'layout-preset-' . $appId, 'secretAccessKey' => 'layout-preset-' . $appId],
            'aclRole' => ['name' => $appName],
        ]], Context::createDefaultContext());
    }

    private function createPreset(string $presetId, string $appId, string $presetName): void
    {
        $this->presetRepository()->create([[
            'id' => $presetId,
            'appId' => $appId,
            'name' => $presetName,
            'schema' => ['name' => 'Hero', 'description' => 'A hero.', 'icon' => 'regular-star', 'layout' => []],
            'hash' => 'hero-hash',
        ]], Context::createDefaultContext());
    }

    private function loader(): DatabaseLayoutPresetLoader
    {
        $compiler = static::getContainer()->get(LayoutPresetPayloadCompiler::class);
        static::assertInstanceOf(LayoutPresetPayloadCompiler::class, $compiler);

        $validator = static::getContainer()->get('validator');
        static::assertInstanceOf(ValidatorInterface::class, $validator);

        $connection = static::getContainer()->get(Connection::class);
        static::assertInstanceOf(Connection::class, $connection);

        return new DatabaseLayoutPresetLoader(
            new LayoutPresetSpecificationSerializer(),
            $compiler,
            $validator,
            $connection,
            'prod',
        );
    }

    /**
     * @return EntityRepository<AppCollection>
     */
    private function appRepository(): EntityRepository
    {
        $repository = static::getContainer()->get('app.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }

    /**
     * @return EntityRepository<AppContentSystemLayoutPresetCollection>
     */
    private function presetRepository(): EntityRepository
    {
        $repository = static::getContainer()->get('app_content_system_layout_preset.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
