<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Layout\Element\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;

/**
 * @internal
 */
#[CoversClass(ContextDefinitions::class)]
class ContextDefinitionsTest extends TestCase
{
    #[TestDox('merges providers immutably and overwrites on key collision')]
    public function testWithAddedProvidersMergesImmutablyAndOverwritesOnCollision(): void
    {
        $existingProvider = new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple());
        $additionalProvider = new ContextProvider(ContextType::Collection, BroadcastDistributionConfig::simple());
        $replacementProvider = new ContextProvider(ContextType::Collection, BroadcastDistributionConfig::simple());

        $original = new ContextDefinitions(
            providers: ['product' => $existingProvider, 'category' => $existingProvider],
        );

        $merged = $original->withAddedProviders([
            'category' => $additionalProvider,
            'region' => $replacementProvider,
        ]);

        static::assertNotSame($original, $merged);
        static::assertSame(
            ['product' => $existingProvider, 'category' => $additionalProvider, 'region' => $replacementProvider],
            $merged->getAllProviders()
        );
        static::assertSame(
            ['product' => $existingProvider, 'category' => $existingProvider],
            $original->getAllProviders()
        );
    }

    #[TestDox('returns new instance with unchanged providers when merging empty array')]
    public function testWithAddedProvidersWithEmptyArrayReturnsNewInstanceWithUnchangedProviders(): void
    {
        $existingProvider = new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple());

        $original = new ContextDefinitions(
            providers: ['product' => $existingProvider, 'category' => $existingProvider],
        );

        $result = $original->withAddedProviders([]);

        static::assertNotSame($original, $result);
        static::assertSame(
            ['product' => $existingProvider, 'category' => $existingProvider],
            $result->getAllProviders()
        );
    }
}
