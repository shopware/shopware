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
            'category' => $additionalProvider,  // overwrites existing key
            'region' => $replacementProvider,   // adds new key
        ]);

        static::assertSame(
            ['product' => $existingProvider, 'category' => $additionalProvider, 'region' => $replacementProvider],
            $merged->getAllProviders()
        );
        static::assertNotSame($original, $merged);
        static::assertSame(
            ['product' => $existingProvider, 'category' => $existingProvider],
            $original->getAllProviders()
        );

        $resultFromEmpty = $original->withAddedProviders([]);
        static::assertNotSame($original, $resultFromEmpty);
        static::assertSame(
            ['product' => $existingProvider, 'category' => $existingProvider],
            $resultFromEmpty->getAllProviders()
        );
    }
}
