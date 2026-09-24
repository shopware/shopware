<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Symfony\XmlServiceMapFactory;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation\DeprecatedServiceFeatureTagRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<DeprecatedServiceFeatureTagRule>
 */
#[Package('framework')]
class DeprecatedServiceFeatureTagRuleTest extends RuleTestCase
{
    #[RunInSeparateProcess]
    public function testDeprecatedServicesNeedMatchingInactiveFeatureTags(): void
    {
        $directory = __DIR__ . '/data/DeprecatedMethodsThrowDeprecationRule';

        $this->analyse([
            $directory . '/DeprecatedClass.php',
            $directory . '/DeprecatedDecorator.php',
            $directory . '/TaggedDeprecatedClass.php',
        ], [
            [
                'Service "Shopware\\Core\\DevOps\\MyFakeNamespace\\DeprecatedClass" uses deprecated class "Shopware\\Core\\DevOps\\MyFakeNamespace\\DeprecatedClass" and must be tagged "shopware.inactiveFeature" for "v6.8.0.0".',
                10,
            ],
            [
                'Service "Shopware\\Core\\DevOps\\MyFakeNamespace\\DeprecatedDecorator" uses deprecated class "Shopware\\Core\\DevOps\\MyFakeNamespace\\DeprecatedDecorator" and must be tagged "shopware.inactiveFeature" for "v6.8.0.0".',
                8,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        /** @phpstan-ignore phpstanApi.constructor */
        $factory = new XmlServiceMapFactory(__DIR__ . '/data/DeprecatedMethodsThrowDeprecationRule/container.xml');

        /** @phpstan-ignore phpstanApi.method */
        return new DeprecatedServiceFeatureTagRule($factory->create());
    }
}
