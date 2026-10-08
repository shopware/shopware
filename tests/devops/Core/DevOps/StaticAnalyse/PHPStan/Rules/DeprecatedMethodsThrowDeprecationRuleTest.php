<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Symfony\XmlServiceMapFactory;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation\DeprecatedExceptionPattern;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation\DeprecatedMethodsThrowDeprecationRule;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation\DeprecatedServiceDecoratorPattern;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<DeprecatedMethodsThrowDeprecationRule>
 */
#[Package('framework')]
class DeprecatedMethodsThrowDeprecationRuleTest extends RuleTestCase
{
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

    public function testRemovedExceptionsGuardConstructionAndFactoriesButNotMetadata(): void
    {
        $this->analyse([__DIR__ . '/data/DeprecatedMethodsThrowDeprecationRule/RemovedExceptions.php'], [
            ['Deprecated framework method "Shopware\\Core\\DevOps\\MyFakeNamespace\\RemovedException::getErrorCode" must remain callable without a deprecation guard.', 23],
            ['Deprecated framework method "Shopware\\Core\\DevOps\\MyFakeNamespace\\RemovedException::missingFactoryGuard" must start with "Feature::throwIfActive" for feature flag "v6.8.0.0".', 30],
            ['Deprecated framework method "Shopware\\Core\\DevOps\\MyFakeNamespace\\ExceptionFactories::wrongFactoryFlag" must start with "Feature::throwIfActive" for feature flag "v6.8.0.0".', 61],
            ['Deprecated framework method "Shopware\\Core\\DevOps\\MyFakeNamespace\\UnguardedException::__construct" must start with "Feature::throwIfActive" for feature flag "v6.8.0.0".', 74],
            ['Deprecated framework method "Shopware\\Core\\DevOps\\MyFakeNamespace\\UnguardedException::getCustomData" must start with "Feature::throwIfActive" for feature flag "v6.8.0.0".', 79],
        ]);
    }

    protected function getRule(): Rule
    {
        /** @phpstan-ignore phpstanApi.constructor */
        $factory = new XmlServiceMapFactory(__DIR__ . '/data/DeprecatedMethodsThrowDeprecationRule/container.xml');

        /** @phpstan-ignore phpstanApi.method */
        $serviceMap = $factory->create();

        return new DeprecatedMethodsThrowDeprecationRule($serviceMap, [
            new DeprecatedExceptionPattern(),
            new DeprecatedServiceDecoratorPattern($serviceMap, self::createReflectionProvider()),
        ]);
    }
}
