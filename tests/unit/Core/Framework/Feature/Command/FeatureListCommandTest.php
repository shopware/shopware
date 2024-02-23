<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Feature\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\Command\FeatureListCommand;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(FeatureListCommand::class)]
class FeatureListCommandTest extends TestCase
{
    public function testName(): void
    {
        $command = new FeatureListCommand();

        static::assertSame('feature:list', $command->getName());
    }

    public function testEmptyFeatures(): void
    {
        $command = new FeatureListCommand();
        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute([]);

        static::assertStringContainsString('[INFO] No features are registered', $commandTester->getDisplay());
        static::assertEquals(Command::SUCCESS, $exitCode);
    }

    public function testFeatureTable(): void
    {
        Feature::registerFeatures([
            'FEATURE_ONE' => [
                'name' => 'Feature 1',
                'default' => true,
                'toggleable' => true,
                'active' => true,
                'major' => true,
                'description' => 'This is a test feature',
            ],
            'FEATURE_TWO' => [
                'name' => 'Feature 2',
                'default' => true,
                'toggleable' => true,
                'active' => false,
                'major' => true,
                'description' => 'This is another feature',
            ],
            'FEATURE_THREE' => [
                'name' => 'Feature 3',
                'default' => true,
                'toggleable' => true,
                'active' => true,
                'major' => true,
            ],
        ]);
        $command = new FeatureListCommand();
        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute([]);

        $display = $commandTester->getDisplay();
        static::assertStringContainsString('[INFO] All features that are registered:', $display);
        static::assertMatchesRegularExpression('/Code\s+Name\s+Description\s+Status\s+\n/', $display);
        static::assertMatchesRegularExpression('/FEATURE_ONE\s+Feature 1\s+This is a test feature\s+Enabled\s+\n/', $display);
        static::assertMatchesRegularExpression('/FEATURE_TWO\s+Feature 2\s+This is another feature\s+Disabled\s+\n/', $display);
        static::assertMatchesRegularExpression('/FEATURE_THREE\s+Feature 3\s+\s+Enabled\s+\n/', $display);

        static::assertEquals(Command::SUCCESS, $exitCode);
    }
}
