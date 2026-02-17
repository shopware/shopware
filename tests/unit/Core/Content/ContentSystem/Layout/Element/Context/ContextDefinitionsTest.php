<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Layout\Element\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ContextDefinitions::class)]
class ContextDefinitionsTest extends TestCase
{
    #[TestDox('merges new providers into existing ones')]
    public function testWithAddedProviders(): void
    {
        $existingProvider = new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple());
        $additionalProvider = new ContextProvider(ContextType::Collection, BroadcastDistributionConfig::simple());

        $original = new ContextDefinitions(
            providers: ['product' => $existingProvider],
        );

        $merged = $original->withAddedProviders(['category' => $additionalProvider]);

        static::assertSame(
            ['product' => $existingProvider, 'category' => $additionalProvider],
            $merged->getAllProviders()
        );
    }

    #[TestDox('returns a new instance without mutating the original when adding providers')]
    public function testWithAddedProvidersIsImmutable(): void
    {
        $existingProvider = new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple());
        $additionalProvider = new ContextProvider(ContextType::Collection, BroadcastDistributionConfig::simple());

        $original = new ContextDefinitions(
            providers: ['product' => $existingProvider],
        );

        $merged = $original->withAddedProviders(['category' => $additionalProvider]);

        static::assertNotSame($original, $merged);
        static::assertSame(['product' => $existingProvider], $original->getAllProviders());
        static::assertCount(2, $merged->getAllProviders());
    }
}
