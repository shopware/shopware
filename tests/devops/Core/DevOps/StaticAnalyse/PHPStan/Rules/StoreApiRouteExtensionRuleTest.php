<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\StoreApiRouteExtensionRule;
use Shopware\Core\Framework\Log\Package;

require_once __DIR__ . '/data/StoreApiRouteExtensionRule/routes.php';

/**
 * @internal
 *
 * @extends RuleTestCase<StoreApiRouteExtensionRule>
 */
#[Package('framework')]
class StoreApiRouteExtensionRuleTest extends RuleTestCase
{
    public function testStoreApiRoutesUseExtensions(): void
    {
        $this->analyse([__DIR__ . '/data/StoreApiRouteExtensionRule/routes.php'], [
            ['Store API route StoreApiRouteExtensionRuleFixtures\\Routes::missing must return ExtensionDispatcher::publish() with an Extension object and a private route-body method.', 42],
            ['Store API route StoreApiRouteExtensionRuleFixtures\\Routes::partial must return ExtensionDispatcher::publish() with an Extension object and a private route-body method.', 48],
            ['Store API route StoreApiRouteExtensionRuleFixtures\\Routes::publicBody must return ExtensionDispatcher::publish() with an Extension object and a private route-body method.', 56],
            ['Store API route StoreApiRouteExtensionRuleFixtures\\Routes::closure must return ExtensionDispatcher::publish() with an Extension object and a private route-body method.', 62],
            ['Store API route StoreApiRouteExtensionRuleFixtures\\Routes::wrongExtension must return ExtensionDispatcher::publish() with an Extension object and a private route-body method.', 68],
            ['Store API route StoreApiRouteExtensionRuleFixtures\\MethodScopedRoute::load must return ExtensionDispatcher::publish() with an Extension object and a private route-body method.', 93],
            ['Store API route StoreApiRouteExtensionRuleFixtures\\LegacyRoute::newEndpoint must return ExtensionDispatcher::publish() with an Extension object and a private route-body method.', 119],
            ['Store API route StoreApiRouteExtensionRuleFixtures\\DecoratedRoute::load must use extension events instead of an abstract route/decorator contract.', 139],
            ['Store API route StoreApiRouteExtensionRuleFixtures\\WrongDispatcherRoute::load must return ExtensionDispatcher::publish() with an Extension object and a private route-body method.', 161],
            ['Store API route StoreApiRouteExtensionRuleFixtures\\RepeatedRouteAttributes::load must return ExtensionDispatcher::publish() with an Extension object and a private route-body method.', 175],
            ['Store API route StoreApiRouteExtensionRuleFixtures\\InheritedContractRoute::load must use extension events instead of an abstract route/decorator contract.', 191],
        ]);
    }

    public function testIgnoresTestNamespaces(): void
    {
        $this->analyse([__DIR__ . '/data/StoreApiRouteExtensionRule/test-route.php'], []);
    }

    protected function getRule(): Rule
    {
        return new StoreApiRouteExtensionRule(['StoreApiRouteExtensionRuleFixtures\\LegacyRoute::load']);
    }
}
