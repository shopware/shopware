<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Rule\DaysSinceLastLoginRule;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @package business-ops
 *
 * @internal
 * @group rules
 * @covers \Shopware\Core\Checkout\Customer\Rule\DaysSinceLastLoginRule
 */
class DaysSinceLastLoginRuleTest extends TestCase
{
    use IntegrationTestBehaviour;

    private DaysSinceLastLoginRule $rule;

    protected function setUp(): void
    {
        $this->rule = new DaysSinceLastLoginRule();
    }

    public function testValidateWithMissingParameters(): void
    {
        try {
            $this->getContainer()->get('rule_condition.repository')->create([
                [
                    'type' => $this->rule->getName(),
                    'ruleId' => Uuid::randomHex(),
                ],
            ], Context::createDefaultContext());
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(2, $exceptions);
            static::assertSame('/0/value/operator', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);

            static::assertSame('/0/value/daysPassed', $exceptions[1]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[1]['code']);
        }
    }

    public function testValidateWithIllegalParameters(): void
    {
        try {
            $this->getContainer()->get('rule_condition.repository')->create([
                [
                    'type' => $this->rule->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => ['operator' => 'foo', 'daysPassed' => false],
                ],
            ], Context::createDefaultContext());
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(3, $exceptions);
            static::assertSame('/0/value/operator', $exceptions[0]['source']['pointer']);
            static::assertSame(Choice::NO_SUCH_CHOICE_ERROR, $exceptions[0]['code']);

            static::assertSame('/0/value/daysPassed', $exceptions[2]['source']['pointer']);
            static::assertSame(Type::INVALID_TYPE_ERROR, $exceptions[2]['code']);
        }
    }
}
