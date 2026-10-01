<?php

declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\PropertyNativeTypeRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends  RuleTestCase<PropertyNativeTypeRule>
 */
#[Package('framework')]
class PropertyNativeTypeRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/data/PropertyNativeTypeRule/PropertiesCorrectlyTyped.php'], []);

        $this->analyse([__DIR__ . '/data/PropertyNativeTypeRule/PropertiesNotTyped.php'], [
            ['Native type for property "stringPropertyNotTyped" is missing', 12],
            ['Native type for property "promotedStringPropertyNotTyped" is missing', 18],
        ]);

        $this->analyse([__DIR__ . '/data/PropertyNativeTypeRule/PromotedPropertiesNotTypedWithoutDocBlock.php'], [
            ['Native type for property "promotedStringPropertyNotTyped" is missing', 10],
        ]);
    }

    protected function getRule(): Rule
    {
        return new PropertyNativeTypeRule();
    }
}
