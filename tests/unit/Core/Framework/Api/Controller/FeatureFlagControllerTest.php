<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Api\Controller\FeatureFlagController;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureFlagRegistry;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(FeatureFlagController::class)]
class FeatureFlagControllerTest extends TestCase
{
    public function testEnable(): void
    {
        $featureFlagService = $this->createMock(FeatureFlagRegistry::class);
        $featureFlagService->expects(static::once())->method('enable')->with('foo');

        $cacheClearer = $this->createMock(CacheClearer::class);
        $cacheClearer->expects(static::once())->method('clear');

        $controller = new FeatureFlagController($featureFlagService, $cacheClearer);
        $controller->enable('foo');
    }

    public function testDisable(): void
    {
        $featureFlagService = $this->createMock(FeatureFlagRegistry::class);
        $featureFlagService->expects(static::once())->method('disable')->with('foo');

        $cacheClearer = $this->createMock(CacheClearer::class);
        $cacheClearer->expects(static::once())->method('clear');

        $controller = new FeatureFlagController($featureFlagService, $cacheClearer);
        $controller->disable('foo');
    }

    public function testLoad(): void
    {
        $featureFlags = [
            'FOO' => [
                'name' => 'Foo',
                'default' => true,
                'toggleable' => true,
                'active' => false,
                'major' => true,
                'description' => 'This is a test feature',
            ],
            'BAR' => [
                'name' => 'Bar',
                'default' => true,
                'toggleable' => true,
                'active' => false,
                'major' => false,
                'description' => 'This is another test feature',
            ],
        ];

        Feature::registerFeatures($featureFlags);

        $featureFlagService = $this->createMock(FeatureFlagRegistry::class);
        $featureFlagService->expects(static::never())->method('disable')->with('foo');

        $controller = new FeatureFlagController(
            $featureFlagService,
            $this->createMock(CacheClearer::class)
        );

        $response = $controller->load();

        static::assertSame($featureFlags, json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }
}
