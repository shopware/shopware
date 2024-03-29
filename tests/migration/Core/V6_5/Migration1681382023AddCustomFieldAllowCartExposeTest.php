<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\LineItemCustomFieldRule;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_5\Migration1681382023AddCustomFieldAllowCartExpose;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal
 */
#[CoversClass(Migration1681382023AddCustomFieldAllowCartExpose::class)]
class Migration1681382023AddCustomFieldAllowCartExposeTest extends TestCase
{
    use KernelTestBehaviour;

    private const CUSTOM_FIELD_A = 'migration_test_foo';
    private const CUSTOM_FIELD_B = 'migration_test_bar';
    private const CUSTOM_FIELD_C = 'migration_test_baz';

    public function testColumnGetsCreatedAndExposeSetting(): void
    {
        /** @var Connection $con */
        $con = $this->getContainer()->get(Connection::class);

        if ($this->columnExists($con)) {
            $con->executeStatement('ALTER TABLE `custom_field` DROP `allow_cart_expose`;');
        }

        static::assertFalse($this->columnExists($con));

        $m = new Migration1681382023AddCustomFieldAllowCartExpose();
        $m->update($con);

        static::assertTrue($this->columnExists($con));

        $con->setNestTransactionsWithSavepoints(true);
        $con->beginTransaction();

        $this->createCustomFields();
        $this->createRule();

        static::assertEquals(['0', '0', '0'], $this->getExposeSettings($con));

        $m->update($con);

        static::assertEquals(['1', '0', '1'], $this->getExposeSettings($con));

        $con->rollBack();
    }

    private function columnExists(Connection $connection): bool
    {
        $field = $connection->fetchOne(
            'SHOW COLUMNS FROM `custom_field` WHERE `Field` LIKE :column;',
            ['column' => 'allow_cart_expose']
        );

        return $field === 'allow_cart_expose';
    }

    /**
     * @return mixed[]
     */
    private function getExposeSettings(Connection $connection): array
    {
        return $connection->fetchFirstColumn(
            'SELECT `allow_cart_expose` FROM `custom_field` WHERE `name` IN (:names) ORDER BY `name` ASC;',
            [
                'names' => [self::CUSTOM_FIELD_A, self::CUSTOM_FIELD_B, self::CUSTOM_FIELD_C],
            ],
            [
                'names' => ArrayParameterType::STRING,
            ]
        );
    }

    private function createCustomFields(): void
    {
        $customField = [
            'name' => self::CUSTOM_FIELD_A,
            'type' => CustomFieldTypes::TEXT,
            'config' => [
                'componentName' => 'sw-field',
                'customFieldPosition' => 1,
                'customFieldType' => CustomFieldTypes::TEXT,
                'type' => CustomFieldTypes::TEXT,
                'label' => [
                    'en-GB' => 'lorem_ipsum',
                    'de-DE' => 'lorem_ipsum',
                ],
            ],
        ];

        $this->getContainer()->get('custom_field.repository')->create([
            $customField,
            array_merge($customField, ['name' => self::CUSTOM_FIELD_B]),
            array_merge($customField, ['name' => self::CUSTOM_FIELD_C]),
        ], Context::createDefaultContext());
    }

    private function createRule(): void
    {
        $conditionA = $conditionB = [
            'type' => (new LineItemCustomFieldRule())->getName(),
            'value' => [
                'renderedField' => [
                    'type' => 'text',
                    'name' => self::CUSTOM_FIELD_A,
                ],
                'selectedField' => 'foo',
                'selectedFieldSet' => 'bar',
                'renderedFieldValue' => 'foo',
                'operator' => LineItemCustomFieldRule::OPERATOR_EQ,
            ],
        ];
        $conditionB['value']['renderedField']['name'] = self::CUSTOM_FIELD_B;

        $this->getContainer()->get('rule.repository')->create([
            [
                'name' => 'test',
                'priority' => 1,
                'type' => 'true',
                'conditions' => [
                    $conditionA,
                    $conditionB,
                ],
            ],
        ], Context::createDefaultContext());
    }
}
