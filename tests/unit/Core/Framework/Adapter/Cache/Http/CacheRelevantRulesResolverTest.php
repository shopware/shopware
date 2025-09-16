<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheRelevantRulesResolver;
use Shopware\Core\Framework\Adapter\Cache\Http\Extension\ResolveCacheRelevantRuleIdsExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\EventDispatcher\AssertingEventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CacheRelevantRulesResolver::class)]
class CacheRelevantRulesResolverTest extends TestCase
{
    public function testResolveRuleAreas(): void
    {
        $eventDispatcher = new AssertingEventDispatcher(
            $this,
            [
                ResolveCacheRelevantRuleIdsExtension::NAME . '.pre' => 1,
                ResolveCacheRelevantRuleIdsExtension::NAME . '.post' => 1,
            ]
        );

        $resolver = new CacheRelevantRulesResolver(new ExtensionDispatcher(
            $eventDispatcher
        ));

        $ruleAreas = $resolver->resolveRuleAreas(
            new Request(),
            $this->createMock(SalesChannelContext::class)
        );

        static::assertSame([RuleAreas::PRODUCT_AREA], $ruleAreas);
    }
}
