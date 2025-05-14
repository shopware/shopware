<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Elasticsearch\Event\ElasticsearchCustomFieldsMappingEvent;
use Shopware\Elasticsearch\Framework\ElasticsearchIndexingUtils;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(ElasticsearchIndexingUtils::class)]
class ElasticsearchIndexingUtilsTest extends TestCase
{
    public function testGetCustomFieldTypes(): void
    {
        $dispatcher = new EventDispatcher();

        $customFieldsMappingEventDispatched = 0;

        $dispatcher->addListener(ElasticsearchCustomFieldsMappingEvent::class, function (ElasticsearchCustomFieldsMappingEvent $event) use (&$customFieldsMappingEventDispatched): void {
            ++$customFieldsMappingEventDispatched;
        });

        $parameterBag = new ParameterBag(['elasticsearch.product.custom_fields_mapping' => [
            'cf_foo' => 'text',
            'cf_baz' => 'int',
        ]]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAllKeyValue')->willReturn([
            'cf_bool' => 'bool',
        ]);

        $utils = new ElasticsearchIndexingUtils(
            $connection,
            $dispatcher,
            $parameterBag,
        );

        // run twice to make sure memoize works
        $formatted = $utils->getCustomFieldTypes(ProductDefinition::ENTITY_NAME, new Context(new SystemSource()));
        $utils->getCustomFieldTypes(ProductDefinition::ENTITY_NAME, new Context(new SystemSource()));

        static::assertSame([
            'cf_bool' => 'bool',
            'cf_foo' => 'text',
            'cf_baz' => 'int',
        ], $formatted);
    }

    public function testStripText(): void
    {
        $input1 = '<p>This is <b>bold</b> text.</p>';
        $expected1 = 'This is bold text.';
        $result1 = ElasticsearchIndexingUtils::stripText($input1);
        static::assertSame($expected1, $result1);

        $input2 = 'This is a short text.';
        $result2 = ElasticsearchIndexingUtils::stripText($input2);
        static::assertSame($input2, $result2);

        $input3 = str_repeat('a', 32766);
        $result3 = ElasticsearchIndexingUtils::stripText($input3);
        static::assertSame($input3, $result3);

        $input4 = str_repeat('a', 33000);
        $expected4 = mb_substr($input4, 0, 32766);
        $result4 = ElasticsearchIndexingUtils::stripText($input4);
        static::assertSame($expected4, $result4);
    }

    public function testParseJsonWithValidJson(): void
    {
        $record = [
            'data' => '{"key": "value"}', // Valid JSON string
        ];
        $field = 'data';

        $result = ElasticsearchIndexingUtils::parseJson($record, $field);

        static::assertSame(['key' => 'value'], $result);
    }

    public function testParseJsonWithNonExistField(): void
    {
        $record = [];
        $field = 'data';

        $result = ElasticsearchIndexingUtils::parseJson($record, $field);

        static::assertSame([], $result);
    }

    public function testParseJsonWithInvalidJson(): void
    {
        $record = [
            'data' => 'invalid-json', // Invalid JSON string
        ];
        $field = 'data';

        static::expectException(\JsonException::class);

        ElasticsearchIndexingUtils::parseJson($record, $field);
    }
}
