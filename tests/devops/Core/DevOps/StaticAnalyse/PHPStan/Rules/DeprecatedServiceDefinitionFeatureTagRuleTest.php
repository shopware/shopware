<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation\DeprecatedServiceDefinitionFeatureTagRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<DeprecatedServiceDefinitionFeatureTagRule>
 */
#[Package('framework')]
class DeprecatedServiceDefinitionFeatureTagRuleTest extends RuleTestCase
{
    public function testDeprecatedPhpServiceDefinitionsNeedInactiveFeatureTags(): void
    {
        $this->analyse([__DIR__ . '/data/DeprecatedServiceDefinitionFeatureTagRule/services.php'], [
            [
                'Deprecated service definitions scheduled for "v6.8.0.0" must be tagged "shopware.inactiveFeature" with that flag.',
                8,
            ],
            [
                'Deprecated service definitions scheduled for "v6.8.0.0" must be tagged "shopware.inactiveFeature" with that flag.',
                11,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        return new DeprecatedServiceDefinitionFeatureTagRule();
    }
}
