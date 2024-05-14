<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Rule\FlowRuleScope;
use Shopware\Core\Content\Flow\Rule\OrderDocumentTypeRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Shopware\Core\Test\Generator;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(OrderDocumentTypeRule::class)]
#[Group('rules')]
class OrderDocumentTypeRuleTest extends TestCase
{
    private OrderDocumentTypeRule $rule;

    protected function setUp(): void
    {
        $this->rule = new OrderDocumentTypeRule();
    }

    public function testName(): void
    {
        static::assertSame('orderDocumentType', $this->rule->getName());
    }

    public function testConstraints(): void
    {
        $operators = [
            Rule::OPERATOR_EQ,
            Rule::OPERATOR_NEQ,
            Rule::OPERATOR_EMPTY,
        ];

        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('documentIds', $constraints, 'documentIds constraint not found');
        static::assertArrayHasKey('operator', $constraints, 'operator constraints not found');

        static::assertEquals([new NotBlank(), new ArrayOfUuid()], $constraints['documentIds']);
        static::assertEquals([new NotBlank(), new Choice($operators)], $constraints['operator']);
    }

    /**
     * @param list<string> $selectedDocumentIds
     */
    #[DataProvider('getMatchingValues')]
    public function testOrderDocumentTypeRuleMatching(bool $expected, ?string $documentId, array $selectedDocumentIds, string $operator): void
    {
        $order = new OrderEntity();
        $collection = new DocumentCollection();

        if ($documentId) {
            $document = new DocumentEntity();
            $document->setId(Uuid::randomHex());
            $document->setDocumentTypeId($documentId);
            $collection->add($document);
        }

        $order->setDocuments($collection);
        $cart = Generator::createCart();
        $context = Generator::createSalesChannelContext();
        $scope = new FlowRuleScope($order, $cart, $context);

        $this->rule->assign(['documentIds' => $selectedDocumentIds, 'operator' => $operator]);
        static::assertSame($expected, $this->rule->match($scope));
    }

    public function testInvalidScopeIsFalse(): void
    {
        $invalidScope = $this->createMock(RuleScope::class);
        $this->rule->assign(['documentIds' => [Uuid::randomHex()], 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($invalidScope));
    }

    public function testDocumentsEmpty(): void
    {
        $order = new OrderEntity();
        $cart = Generator::createCart();
        $context = Generator::createSalesChannelContext();
        $scope = new FlowRuleScope($order, $cart, $context);

        $this->rule->assign(['documentIds' => [Uuid::randomHex()], 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($scope));
    }

    public function testConfig(): void
    {
        $config = (new OrderDocumentTypeRule())->getConfig();
        $configData = $config->getData();

        static::assertArrayHasKey('operatorSet', $configData);
        $operators = RuleConfig::OPERATOR_SET_STRING;
        $operators[] = Rule::OPERATOR_EMPTY;

        static::assertEquals([
            'operators' => $operators,
            'isMatchAny' => true,
        ], $configData['operatorSet']);
    }

    /**
     * @return array<string, array{bool, string|null, list<string>, string}>
     */
    public static function getMatchingValues(): array
    {
        $id = Uuid::randomHex();

        return [
            'ONE OF - true' => [true, $id, [$id, Uuid::randomHex()], Rule::OPERATOR_EQ],
            'ONE OF - false' => [false, $id, [Uuid::randomHex()], Rule::OPERATOR_EQ],
            'NONE OF - true' => [true, $id, [Uuid::randomHex()], Rule::OPERATOR_NEQ],
            'NONE OF - false' => [false, $id, [$id, Uuid::randomHex()], Rule::OPERATOR_NEQ],
            'EMPTY - true' => [true, null, [$id], Rule::OPERATOR_EMPTY],
            'EMPTY - false' => [false, $id, [$id], Rule::OPERATOR_EMPTY],
        ];
    }
}
