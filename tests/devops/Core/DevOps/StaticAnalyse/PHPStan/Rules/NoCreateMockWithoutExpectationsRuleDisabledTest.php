<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\NoCreateMockWithoutExpectationsRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NoCreateMockWithoutExpectationsRule>
 */
#[Package('framework')]
class NoCreateMockWithoutExpectationsRuleDisabledTest extends RuleTestCase
{
    public function testEmptyEnabledNamespacesDisableTheRule(): void
    {
        $this->analyse([__DIR__ . '/data/NoCreateMockWithoutExpectationsRule/Cases.php'], []);
    }

    protected function getRule(): Rule
    {
        return new NoCreateMockWithoutExpectationsRule(
            new Configuration([
                'allowedUnitTestClassNamespaces' => ['Shopware\\Tests\\Unit\\'],
                'createMockWithoutExpectationsEnabledNamespaces' => [],
            ]),
            self::getContainer()->getService('defaultAnalysisParser'),
        );
    }
}
