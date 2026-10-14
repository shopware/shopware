<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Symfony\XmlServiceMapFactory;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation\DeprecatedFrameworkMethodPattern;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation\DeprecatedMethodsThrowDeprecationRule;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation\DeprecatedServiceDecoratorPattern;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 *
 * @extends RuleTestCase<DeprecatedMethodsThrowDeprecationRule>
 */
#[Package('framework')]
class DeprecatedMethodsThrowDeprecationRuleTest extends RuleTestCase
{
    private bool $customFrameworkPolicy = false;

    public function testDeprecatedMethodsReportMissingDeprecationTrigger(): void
    {
        $this->analyse([__DIR__ . '/data/DeprecatedMethodsThrowDeprecationRule/DeprecatedMethods.php'], [
            [
                'Method "__invoke" of class "Shopware\Core\DevOps\MyFakeNamespace\DeprecatedMethods" is marked as deprecated, but does not call "Feature::triggerDeprecationOrThrow". All deprecated methods need to trigger a deprecation warning.',
                12,
            ],
            [
                'Method "deprecatedWithoutTrigger" of class "Shopware\Core\DevOps\MyFakeNamespace\DeprecatedMethods" is marked as deprecated, but does not call "Feature::triggerDeprecationOrThrow". All deprecated methods need to trigger a deprecation warning.',
                19,
            ],
            [
                'Method "deprecatedWithRemovedReasons" of class "Shopware\Core\DevOps\MyFakeNamespace\DeprecatedMethods" is marked as deprecated, but does not call "Feature::triggerDeprecationOrThrow". All deprecated methods need to trigger a deprecation warning.',
                26,
            ],
            [
                'Method "deprecatedWithExceptedReason" of class "Shopware\Core\DevOps\MyFakeNamespace\DeprecatedMethods" is marked as deprecated, but does not call "Feature::triggerDeprecationOrThrow". All deprecated methods need to trigger a deprecation warning.',
                44,
            ],
        ]);
    }

    public function testDeprecatedClassesReportMissingDeprecationTriggerInPublicMethods(): void
    {
        $this->analyse([__DIR__ . '/data/DeprecatedMethodsThrowDeprecationRule/DeprecatedClass.php'], [
            [
                'Class "Shopware\Core\DevOps\MyFakeNamespace\DeprecatedClass" is marked as deprecated, but method "publicMethodWithoutTrigger" does not call "Feature::triggerDeprecationOrThrow". All public methods of deprecated classes need to trigger a deprecation warning.',
                16,
            ],
        ]);
    }

    public function testDeprecatedServiceDecoratorsMustDelegateToTheInnerServiceWhenTheFeatureFlagIsActive(): void
    {
        $this->analyse([__DIR__ . '/data/DeprecatedMethodsThrowDeprecationRule/DeprecatedDecorator.php'], [
            [
                'Class decorator "Shopware\\Core\\DevOps\\MyFakeNamespace\\DeprecatedDecorator" is marked as deprecated, but method "doesNotDelegateToInner" does not call "Feature::triggerDeprecationOrThrow". Methods not declared by the decorated service need to trigger a deprecation warning.',
                25,
            ],
            [
                'Method "explicitlyDeprecatedMethod" of class "Shopware\\Core\\DevOps\\MyFakeNamespace\\DeprecatedDecorator" is marked as deprecated, but does not call "Feature::triggerDeprecationOrThrow". All deprecated methods need to trigger a deprecation warning.',
                56,
            ],
        ]);
    }

    public function testInactiveFeatureServicesStillNeedClassDeprecationTriggers(): void
    {
        $this->analyse([__DIR__ . '/data/DeprecatedMethodsThrowDeprecationRule/TaggedDeprecatedClass.php'], [
            [
                'Class "Shopware\\Core\\DevOps\\MyFakeNamespace\\TaggedDeprecatedClass" is marked as deprecated, but method "frameworkInvokedMethod" does not call "Feature::triggerDeprecationOrThrow". All public methods of deprecated classes need to trigger a deprecation warning.',
                10,
            ],
            [
                'Class "Shopware\\Core\\DevOps\\MyFakeNamespace\\TaggedDeprecatedClass" is marked as deprecated, but method "explicitlyDeprecatedMethod" does not call "Feature::triggerDeprecationOrThrow". All public methods of deprecated classes need to trigger a deprecation warning.',
                17,
            ],
        ]);
    }

    public function testFrameworkCallbacksUseTheirSpecificDeprecationPolicy(): void
    {
        $this->analyse([__DIR__ . '/data/DeprecatedMethodsThrowDeprecationRule/FrameworkCallbacks.php'], [
            ['Deprecated framework method "Shopware\\Core\\DevOps\\MyFakeNamespace\\GuardedSubscriber::missingGuard" must start with "Feature::throwIfActive" for feature flag "v6.8.0.0".', 34],
            ['Deprecated framework method "Shopware\\Core\\DevOps\\MyFakeNamespace\\GuardedSubscriber::wrongFlag" must start with "Feature::throwIfActive" for feature flag "v6.8.0.0".', 38],
            ['Deprecated framework method "Shopware\\Core\\DevOps\\MyFakeNamespace\\GuardedSubscriber::lateGuard" must start with "Feature::throwIfActive" for feature flag "v6.8.0.0".', 43],
            ['Deprecated framework method "Shopware\\Core\\DevOps\\MyFakeNamespace\\WrongDiscoveryFlag::getSubscribedEvents" must return [] when feature flag "v6.9.0.0" is active.', 55],
            ['Deprecated framework method "Shopware\\Core\\DevOps\\MyFakeNamespace\\NonemptyDiscovery::getSubscribedEvents" must return [] when feature flag "v6.8.0.0" is active.', 70],
            ['Deprecated framework method "Shopware\\Core\\DevOps\\MyFakeNamespace\\GuardedHandler::getPriority" must remain callable without a deprecation guard.', 106],
            ['Class "Shopware\\Core\\DevOps\\MyFakeNamespace\\GuardedTwigExtension" is marked as deprecated, but method "exposedFunction" does not call "Feature::triggerDeprecationOrThrow". All public methods of deprecated classes need to trigger a deprecation warning.', 142],
            ['Deprecated framework method "Shopware\\Core\\DevOps\\MyFakeNamespace\\UnsafeReset::reset" must remain callable without a deprecation guard.', 153],
            ['Deprecated framework method "Shopware\\Core\\DevOps\\MyFakeNamespace\\GuardedListener::extraPublicMethod" must start with "Feature::throwIfActive" for feature flag "v6.8.0.0".', 169],
        ]);
    }

    public function testRemovedRulesHaveNeutralConfigurationAndGuardedRuntimeMethods(): void
    {
        $this->analyse([__DIR__ . '/data/DeprecatedMethodsThrowDeprecationRule/RemovedRules.php'], [
            ['Deprecated framework method "Shopware\\Core\\DevOps\\MyFakeNamespace\\WrongRuleConfig::getConfig" must return null when feature flag "v6.8.0.0" is active.', 44],
        ]);
    }

    public function testFrameworkPoliciesCanBeConfigured(): void
    {
        $this->customFrameworkPolicy = true;
        $this->analyse([__DIR__ . '/data/DeprecatedMethodsThrowDeprecationRule/CustomFrameworkPolicy.php'], [
            ['Deprecated framework method "Shopware\\Core\\DevOps\\MyFakeNamespace\\CustomFrameworkPolicy::unguardedHandler" must start with "Feature::throwIfActive" for feature flag "v6.9.0.0".', 31],
        ]);
    }

    protected function getRule(): Rule
    {
        /** @phpstan-ignore phpstanApi.constructor */
        $factory = new XmlServiceMapFactory(__DIR__ . '/data/DeprecatedMethodsThrowDeprecationRule/container.xml');

        /** @phpstan-ignore phpstanApi.method */
        $serviceMap = $factory->create();

        $frameworkPattern = $this->customFrameworkPolicy
            ? new DeprecatedFrameworkMethodPattern($serviceMap, [ResetInterface::class => ['discover' => null, 'reset' => true]], [ResetInterface::class], [])
            : new DeprecatedFrameworkMethodPattern($serviceMap);

        return new DeprecatedMethodsThrowDeprecationRule($serviceMap, [
            $frameworkPattern,
            new DeprecatedServiceDecoratorPattern($serviceMap, self::createReflectionProvider()),
        ]);
    }
}
