<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Symfony\XmlServiceMapFactory;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation\DeprecatedMethodsThrowDeprecationRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<DeprecatedMethodsThrowDeprecationRule>
 */
#[Package('framework')]
class DeprecatedMethodsThrowDeprecationRuleTest extends RuleTestCase
{
    #[RunInSeparateProcess]
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
        ]);
    }

    #[RunInSeparateProcess]
    public function testDeprecatedClassesReportMissingDeprecationTriggerInPublicMethods(): void
    {
        $this->analyse([__DIR__ . '/data/DeprecatedMethodsThrowDeprecationRule/DeprecatedClass.php'], [
            [
                'Class "Shopware\Core\DevOps\MyFakeNamespace\DeprecatedClass" is marked as deprecated, but method "publicMethodWithoutTrigger" does not call "Feature::triggerDeprecationOrThrow". All public methods of deprecated classes need to trigger a deprecation warning.',
                16,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        /** @phpstan-ignore phpstanApi.constructor */
        $factory = new XmlServiceMapFactory(__DIR__ . '/data/DeprecatedMethodsThrowDeprecationRule/container.xml');

        /** @phpstan-ignore phpstanApi.method */
        return new DeprecatedMethodsThrowDeprecationRule($factory->create());
    }
}
