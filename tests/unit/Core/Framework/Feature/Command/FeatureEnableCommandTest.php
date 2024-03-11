<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Feature\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\Command\FeatureEnableCommand;
use Shopware\Core\Framework\Feature\FeatureFlagRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\Framework\Adapter\Storage\ArrayKeyValueStorage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(FeatureEnableCommand::class)]
class FeatureEnableCommandTest extends TestCase
{
    public function testName(): void
    {
        $command = new FeatureEnableCommand(
            $this->createMock(FeatureFlagRegistry::class),
            $this->createMock(CacheClearer::class)
        );

        static::assertSame('feature:enable', $command->getName());
    }

    /**
     * @param array<string> $args
     * @param array<string> $featuresToEnable
     */
    #[DataProvider('featureProvider')]
    public function testEnableFeature(array $args, array $featuresToEnable): void
    {
        Feature::registerFeatures([
            'FEATURE_ONE' => [
                'name' => 'Feature 1',
                'default' => true,
                'toggleable' => true,
                'active' => false,
                'description' => 'This is a test feature',
            ],
            'FEATURE_TWO' => [
                'name' => 'Feature 2',
                'default' => true,
                'toggleable' => true,
                'active' => false,
                'description' => 'This is another feature',
            ],
            'FEATURE_THREE' => [
                'name' => 'Feature 3',
                'default' => true,
                'toggleable' => true,
                'active' => false,
            ],
        ]);

        foreach ($featuresToEnable as $feature) {
            static::assertFalse(Feature::isActive($feature));
        }

        $cacheClearer = $this->createMock(CacheClearer::class);
        $cacheClearer->expects(static::once())->method('clear');

        $storage = new ArrayKeyValueStorage();
        $registry = new FeatureFlagRegistry($storage, new EventDispatcher(), [], true);

        $command = new FeatureEnableCommand($registry, $cacheClearer);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['features' => $args]);

        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        foreach ($featuresToEnable as $feature) {
            static::assertTrue(Feature::isActive($feature));
        }
    }

    /**
     * @return array<string, array{0: array<string>, 1: array<string>}>
     */
    public static function featureProvider(): array
    {
        return [
            'one-feature' => [['FEATURE_ONE'], ['FEATURE_ONE']],
            'multiple-feature' => [['FEATURE_ONE', 'FEATURE_TWO'], ['FEATURE_ONE', 'FEATURE_TWO']],
            'duplicate-features' => [['FEATURE_ONE', 'FEATURE_ONE'], ['FEATURE_ONE']],
        ];
    }
}
