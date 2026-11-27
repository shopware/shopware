<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\NoXmlUtilsParsingRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NoXmlUtilsParsingRule>
 */
#[Package('framework')]
class NoXmlUtilsParsingRuleTest extends RuleTestCase
{
    public function testRuleReportsParsingThroughXmlUtils(): void
    {
        $this->analyse([__DIR__ . '/data/NoXmlUtilsParsingRule/ParsesXml.php'], [
            [\sprintf(NoXmlUtilsParsingRule::ERROR_MESSAGE, 'parse', 'parse'), 12],
            [\sprintf(NoXmlUtilsParsingRule::ERROR_MESSAGE, 'loadFile', 'loadFile'), 13],
        ]);
    }

    public function testRuleIgnoresTestClasses(): void
    {
        $this->analyse([__DIR__ . '/data/NoXmlUtilsParsingRule/ParsesXmlInTestClass.php'], []);
    }

    public function testRuleIgnoresTheHardenedParser(): void
    {
        $this->analyse([__DIR__ . '/../../../../../../../src/Core/Framework/Util/XmlParser.php'], []);
    }

    protected function getRule(): Rule
    {
        return new NoXmlUtilsParsingRule();
    }
}
