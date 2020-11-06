<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal\FieldAccessorBuilder;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\JsonFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class JsonFieldAccessorBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testHyphenatedFieldAccessor(): void
    {
        $fieldName = 'aJsonField.my-hyphenated-field';

        $accessorBuilder = new JsonFieldAccessorBuilder($this->getContainer()->get(Connection::class));
        $jsonField = new JsonField('a_json_field', 'aJsonField');

        static::assertEquals('IF(JSON_TYPE(JSON_EXTRACT(`some_entity`.`a_json_field`, \'$.\\"my-hyphenated-field\\"\')) != "NULL", CONVERT(JSON_UNQUOTE(JSON_EXTRACT(`some_entity`.`a_json_field`, \'$.\\"my-hyphenated-field\\"\')) USING "utf8mb4") COLLATE utf8mb4_unicode_ci, NULL)', $accessorBuilder->buildAccessor('some_entity', $jsonField, Context::createDefaultContext(), $fieldName));
    }

    public function testNestedFieldsAccessor(): void
    {
        $fieldName = 'aJsonField.my.nested.field';

        $accessorBuilder = new JsonFieldAccessorBuilder($this->getContainer()->get(Connection::class));
        $jsonField = new JsonField('a_json_field', 'aJsonField');

        static::assertEquals('IF(JSON_TYPE(JSON_EXTRACT(`some_entity`.`a_json_field`, \'$.\\"my\\".\\"nested\\".\\"field\\"\')) != "NULL", CONVERT(JSON_UNQUOTE(JSON_EXTRACT(`some_entity`.`a_json_field`, \'$.\\"my\\".\\"nested\\".\\"field\\"\')) USING "utf8mb4") COLLATE utf8mb4_unicode_ci, NULL)', $accessorBuilder->buildAccessor('some_entity', $jsonField, Context::createDefaultContext(), $fieldName));
    }
}
