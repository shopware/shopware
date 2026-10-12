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
                10,
            ],
            [
                'Deprecated service definitions scheduled for "v6.8.0.0" must be tagged "shopware.inactiveFeature" with that flag.',
                13,
            ],
            [
                'Deprecated service definitions scheduled for "v6.8.0.0" must be tagged "shopware.inactiveFeature" with that flag.',
                24,
            ],
            [
                'Deprecated service definitions scheduled for "v6.8.0.0" must be tagged "shopware.inactiveFeature" with that flag.',
                27,
            ],
            [
                'Deprecated service alias "unlisted-alias" scheduled for "v6.8.0.0" must be listed in FeatureFlagCompilerPass::ALIASES_TO_REMOVE.',
                46,
            ],
            [
                'Deprecated service alias "Shopware\Administration\Notification\NotificationDefinition" scheduled for "v6.9.0.0" must be listed in FeatureFlagCompilerPass::ALIASES_TO_REMOVE.',
                49,
            ],
            [
                'Deprecated service aliases must have a constant ID to check the removal flag.',
                59,
            ],
            [
                'Deprecated service definitions scheduled for "v6.8.0.0" must be tagged "shopware.inactiveFeature" with that flag.',
                64,
            ],
            [
                'Deprecated service alias "renamed-alias" scheduled for "v6.8.0.0" must be listed in FeatureFlagCompilerPass::ALIASES_TO_REMOVE.',
                67,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        return new DeprecatedServiceDefinitionFeatureTagRule();
    }
}
