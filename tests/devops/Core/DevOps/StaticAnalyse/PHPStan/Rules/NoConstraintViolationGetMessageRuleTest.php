<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\NoConstraintViolationGetMessageRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NoConstraintViolationGetMessageRule>
 */
#[Package('framework')]
class NoConstraintViolationGetMessageRuleTest extends RuleTestCase
{
    private const ERROR = 'Do not use ConstraintViolationInterface::getMessage(). Use getCode() and translate it through the Shopware translator.';

    public function testGetMessageIsForbiddenAndGetCodeIsAllowed(): void
    {
        $this->analyse([__DIR__ . '/data/NoConstraintViolationGetMessageRule/Usage.php'], [
            [self::ERROR, 13],
        ]);
    }

    protected function getRule(): Rule
    {
        return new NoConstraintViolationGetMessageRule();
    }
}
