<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
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

    #[TestDox('returns only the consumer keys whose scope matches, preserving order')]
    public function testGetConsumerKeysByScopeReturnsOnlyMatchingScopeKeys(): void
    {
        $definitions = new ContextDefinitions(
            consumers: [
                'configuratorSettings' => new ContextConsumer(ContextType::Single, required: false, scope: ConsumerScope::Root),
                'headline' => new ContextConsumer(ContextType::Single, required: true),
                'product' => new ContextConsumer(ContextType::Single, required: false, scope: ConsumerScope::Root),
            ],
        );

        static::assertSame(
            ['configuratorSettings', 'product'],
            $definitions->getConsumerKeysByScope(ConsumerScope::Root)
        );
        static::assertSame(
            ['headline'],
            $definitions->getConsumerKeysByScope(ConsumerScope::Parent)
        );
    }

    #[TestDox('returns an empty list when no consumer has the requested scope')]
    public function testGetConsumerKeysByScopeReturnsEmptyListWhenNoneMatch(): void
    {
        $definitions = new ContextDefinitions(
            consumers: ['headline' => new ContextConsumer(ContextType::Single, required: true)],
        );

        static::assertSame([], $definitions->getConsumerKeysByScope(ConsumerScope::Root));
    }
}
