<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\FeatureFlag;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureFlagRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\FeatureFlag\FeatureFlagProfiler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(FeatureFlagProfiler::class)]
class FeatureFlagProfilerTest extends TestCase
{
    public function testCollect(): void
    {
        Feature::registerFeatures([
            'FEATURE_ABC' => [
                'name' => 'Feature ABC',
                'default' => true,
                'major' => true,
                'description' => 'This is a test feature',
            ],
        ]);
        $featureFlagService = $this->createMock(FeatureFlagRegistry::class);
        $featureFlagService->expects(static::once())->method('register');

        $profiler = new FeatureFlagProfiler($featureFlagService);
        $profiler->collect(new Request(), new Response());

        static::assertSame('feature_flag', $profiler->getName());
        static::assertSame('@Profiling/Collector/flags.html.twig', FeatureFlagProfiler::getTemplate());
        static::assertSame([
            'FEATURE_ABC' => [
                'name' => 'FEATURE_ABC',
                'default' => true,
                'major' => true,
                'description' => 'This is a test feature',
                'active' => true,
            ],
        ], $profiler->getFeatures());
    }

    public function testGetTemplate(): void
    {
        static::assertSame('@Profiling/Collector/flags.html.twig', FeatureFlagProfiler::getTemplate());
    }

    public function testGetName(): void
    {
        $featureFlagService = $this->createMock(FeatureFlagRegistry::class);
        $profiler = new FeatureFlagProfiler($featureFlagService);
        static::assertSame('feature_flag', $profiler->getName());
    }
}
