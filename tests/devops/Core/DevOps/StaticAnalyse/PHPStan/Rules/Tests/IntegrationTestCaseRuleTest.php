<?php

declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\Tests;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\IntegrationTestCaseRule;

/**
 * @internal
 *
 * @extends  RuleTestCase<IntegrationTestCaseRule>
 */
class IntegrationTestCaseRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/../data/IntegrationTestCaseRuleTest/ViolatingTest.php'], [
            [
                'Shopware\Core\DevOps\MyFakeNamespace\ViolatingTest should extend Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestCase instead of using trait Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour directly.',
                8,
                'For PHPStan performance reasons.',
            ],
        ]);
    }

    public function testFix(): void
    {
        $this->fix(
            __DIR__ . '/../data/IntegrationTestCaseRuleTest/ViolatingTest.php',
            __DIR__ . '/../data/IntegrationTestCaseRuleTest/ViolatingTest.php.fixed',
        );
    }

    protected function getRule(): Rule
    {
        return new IntegrationTestCaseRule();
    }
}
