<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp\Scenario;

use PHPUnit\Framework\Attributes\DataProvider;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Validates that entity schemas contain the fields and associations
 * that the MCP user stories depend on. Catches silent renames or removals.
 */
#[Package('framework')]
class SchemaCompletenessTest extends McpScenarioTestCase
{
    /**
     * @return iterable<string, array{string, list<string>, list<string>}>
     */
    public static function entitySchemaProvider(): iterable
    {
        yield 'order' => [
            'order',
            ['orderDateTime', 'amountTotal', 'orderNumber'],
            ['stateMachineState', 'orderCustomer', 'lineItems', 'deliveries', 'transactions', 'currency'],
        ];

        yield 'product' => [
            'product',
            ['stock', 'name', 'productNumber', 'active'],
            ['categories', 'prices', 'tax'],
        ];

        yield 'customer' => [
            'customer',
            ['email', 'firstName', 'lastName', 'customerNumber'],
            ['orderCustomers', 'addresses', 'group'],
        ];

        yield 'flow' => [
            'flow',
            ['name', 'eventName', 'active'],
            ['sequences'],
        ];

        yield 'order_delivery' => [
            'order_delivery',
            [],
            ['stateMachineState'],
        ];

        yield 'order_transaction' => [
            'order_transaction',
            [],
            ['stateMachineState'],
        ];
    }

    /**
     * @param list<string> $expectedFields
     * @param list<string> $expectedAssociations
     */
    #[DataProvider('entitySchemaProvider')]
    public function testEntitySchemaContainsRequiredFieldsAndAssociations(
        string $entity,
        array $expectedFields,
        array $expectedAssociations,
    ): void {
        $output = ($this->entitySchemaTool)($entity);
        $data = $this->decodeToolOutput($output);

        $fieldNames = array_column($data['data']['fields'], 'name');
        foreach ($expectedFields as $field) {
            static::assertContains($field, $fieldNames, \sprintf(
                'Entity "%s" schema is missing field "%s" required by MCP user stories.',
                $entity,
                $field,
            ));
        }

        $assocNames = array_column($data['data']['associations'], 'name');
        foreach ($expectedAssociations as $assoc) {
            static::assertContains($assoc, $assocNames, \sprintf(
                'Entity "%s" schema is missing association "%s" required by MCP user stories.',
                $entity,
                $assoc,
            ));
        }
    }
}
