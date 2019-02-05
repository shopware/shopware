<?php declare(strict_types=1);

namespace SwagCustomRule\Test\Core;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use SwagCustomRule\Core\Rule\Count42Rule;
use SwagCustomRule\SwagCustomRule;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class Count42RuleTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var RuleConditionRegistry
     */
    private $registry;

    protected function setUp(): void
    {
        $this->registry = $this->getContainer()->get(RuleConditionRegistry::class);
    }

    public function testIsPluginRegistered(): void
    {
        $this->getContainer()->get(Count42Rule::class);
    }

    public function testConstraint(): void
    {
        $rule = new Count42Rule();
        $constraints = $rule->getConstraints();
        static::assertEquals(
            [
                'operator' => [new Choice([Count42Rule::OPERATOR_EQ, Count42Rule::OPERATOR_NEQ])],
                'count' => [new NotBlank(), new Type('int')],
            ], $constraints
        );
    }

    public function testName(): void
    {
        $rule = new Count42Rule();
        static::assertSame('swagCount42', $rule->getName());
    }

    public function testRuleIsRegistered(): void
    {
        static::assertContains('swagCount42', $this->registry->getNames(), print_r($this->registry->getNames(), true));
    }
}
