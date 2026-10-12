<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Feature;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Feature\AppFeatureConfig;
use Shopware\Core\Framework\App\Feature\AppFeatureDefinition;
use Shopware\Core\Framework\App\Feature\AppFeatureDefinitionRegistry;
use Shopware\Core\Framework\App\Feature\AppFeatureException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppFeatureDefinitionRegistry::class)]
class AppFeatureDefinitionRegistryTest extends TestCase
{
    public function testForFeatureReturnsTheDefinitionKeyedByItsConfigClass(): void
    {
        $configA = new class implements AppFeatureConfig {
            public function getName(): string
            {
                return 'a';
            }
        };
        $configB = new class implements AppFeatureConfig {
            public function getName(): string
            {
                return 'b';
            }
        };

        $defA = $this->definition($configA::class);
        $defB = $this->definition($configB::class);

        $registry = new AppFeatureDefinitionRegistry([$defA, $defB]);

        static::assertSame($defA, $registry->forFeature($configA::class));
        static::assertSame($defB, $registry->forFeature($configB::class));
    }

    public function testAllReturnsEveryRegisteredDefinition(): void
    {
        $configA = new class implements AppFeatureConfig {
            public function getName(): string
            {
                return 'a';
            }
        };
        $configB = new class implements AppFeatureConfig {
            public function getName(): string
            {
                return 'b';
            }
        };

        $defA = $this->definition($configA::class);
        $defB = $this->definition($configB::class);

        $registry = new AppFeatureDefinitionRegistry([$defA, $defB]);

        static::assertSame([$defA, $defB], $registry->all());
    }

    public function testForFeatureThrowsForAnUnregisteredFeature(): void
    {
        $config = new class implements AppFeatureConfig {
            public function getName(): string
            {
                return 'x';
            }
        };

        $registry = new AppFeatureDefinitionRegistry([]);

        try {
            $registry->forFeature($config::class);
            static::fail('Expected AppFeatureException to be thrown');
        } catch (AppFeatureException $e) {
            static::assertSame(AppFeatureException::APP_FEATURE_UNKNOWN_FEATURE, $e->getErrorCode());
        }
    }

    /**
     * @param class-string<AppFeatureConfig> $configClass
     *
     * @return AppFeatureDefinition<AppFeatureConfig>
     */
    private function definition(string $configClass): AppFeatureDefinition
    {
        $definition = static::createStub(AppFeatureDefinition::class);
        $definition->method('getConfigClass')->willReturn($configClass);

        return $definition;
    }
}
