<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Rule\FlowRuleScope;
use Shopware\Core\Content\Flow\Rule\OrderDocumentTypeSentRule;
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
#[CoversClass(OrderDocumentTypeSentRule::class)]
#[Group('rules')]
class OrderDocumentTypeSentRuleTest extends TestCase
{
    private OrderDocumentTypeSentRule $rule;

    protected function setUp(): void
    {
        $this->rule = new OrderDocumentTypeSentRule();
    }

    public function testName(): void
    {
        static::assertSame('orderDocumentTypeSent', $this->rule->getName());
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

    public function testConstraintsEmpty(): void
    {
        $operators = [
            Rule::OPERATOR_EQ,
            Rule::OPERATOR_NEQ,
            Rule::OPERATOR_EMPTY,
        ];

        $this->rule->assign(['operator' => $operators[2]]);
        $constraints = $this->rule->getConstraints();

        static::assertArrayNotHasKey('documentIds', $constraints, 'documentIds constraint found');
        static::assertArrayHasKey('operator', $constraints, 'operator constraints not found');
        static::assertEquals([new NotBlank(), new Choice($operators)], $constraints['operator']);
    }

    /**
     * @param list<string> $selectedDocumentIds
     */
    #[DataProvider('getMatchingValues')]
    public function testOrderDocumentTypeSentRuleMatching(bool $expected, ?string $documentId, bool $sent, array $selectedDocumentIds, string $operator): void
    {
        $order = new OrderEntity();
        $collection = new DocumentCollection();

        if ($documentId) {
            $document = new DocumentEntity();
            $document->setId(Uuid::randomHex());
            $document->setDocumentTypeId($documentId);
            $document->setSent($sent);
            $collection->add($document);
        }

        $order->setDocuments($collection);
        $cart = Generator::createCart();
        $context = Generator::createSalesChannelContext();
        $scope = new FlowRuleScope($order, $cart, $context);

        $this->rule->assign(['documentIds' => $selectedDocumentIds, 'operator' => $operator]);
        static::assertSame($expected, $this->rule->match($scope));
    }

    /**
     * @throws Exception
     */
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
        $config = (new OrderDocumentTypeSentRule())->getConfig();
        $configData = $config->getData();

        static::assertArrayHasKey('operatorSet', $configData);
        $operators = RuleConfig::OPERATOR_SET_STRING;
        $operators[] = Rule::OPERATOR_EMPTY;

        static::assertEquals([
            'operators' => $operators,
            'isMatchAny' => true,
        ], $configData['operatorSet']);
    }

    public static function getMatchingValues(): \Generator
    {
        $id = Uuid::randomHex();

        yield 'ONE OF - true' => [
            'expected' => true,
            'documentId' => $id,
            'sent' => true,
            'selectedDocumentIds' => [$id, Uuid::randomHex()],
            'operator' => Rule::OPERATOR_EQ,
        ];

        yield 'ONE OF - false' => [
            'expected' => false,
            'documentId' => $id,
            'sent' => true,
            'selectedDocumentIds' => [Uuid::randomHex()],
            'operator' => Rule::OPERATOR_EQ,
        ];

        yield 'ONE OF - false (not sent)' => [
            'expected' => false,
            'documentId' => $id,
            'sent' => false,
            'selectedDocumentIds' => [Uuid::randomHex()],
            'operator' => Rule::OPERATOR_EQ,
        ];

        yield 'NONE OF - true' => [
            'expected' => true,
            'documentId' => $id,
            'sent' => true,
            'selectedDocumentIds' => [Uuid::randomHex()],
            'operator' => Rule::OPERATOR_NEQ,
        ];

        yield 'NONE OF - true (not sent)' => [
            'expected' => true,
            'documentId' => $id,
            'sent' => false,
            'selectedDocumentIds' => [$id, Uuid::randomHex()],
            'operator' => Rule::OPERATOR_NEQ,
        ];

        yield 'NONE OF - false' => [
            'expected' => false,
            'documentId' => $id,
            'sent' => true,
            'selectedDocumentIds' => [$id, Uuid::randomHex()],
            'operator' => Rule::OPERATOR_NEQ,
        ];

        yield 'EMPTY - true' => [
            'expected' => true,
            'documentId' => null,
            'sent' => true,
            'selectedDocumentIds' => [$id],
            'operator' => Rule::OPERATOR_EMPTY,
        ];

        yield 'EMPTY - true (not sent)' => [
            'expected' => true,
            'documentId' => $id,
            'sent' => false,
            'selectedDocumentIds' => [$id],
            'operator' => Rule::OPERATOR_EMPTY,
        ];

        yield 'EMPTY - false' => [
            'expected' => false,
            'documentId' => $id,
            'sent' => true,
            'selectedDocumentIds' => [$id],
            'operator' => Rule::OPERATOR_EMPTY,
        ];
    }
}
