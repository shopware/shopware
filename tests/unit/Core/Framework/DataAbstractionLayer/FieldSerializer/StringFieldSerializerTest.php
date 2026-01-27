<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowEmptyString;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\StringFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(StringFieldSerializer::class)]
class StringFieldSerializerTest extends TestCase
{
    /**
     * Test that StringFieldSerializer strips HTML tags when AllowHtml flag is not set
     */
    #[DataProvider('stripTagsProvider')]
    public function testStripsTagsWhenAllowHtmlNotSet(string|int|null $value, ?string $expected): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $sanitizer = new HtmlSanitizer();

        $serializer = new StringFieldSerializer($validator, $definitionRegistry, $sanitizer);

        $field = new StringField('test', 'test');
        $field->addFlags(new AllowEmptyString());

        $data = new KeyValuePair('test', $value, true);
        $existence = new EntityExistence(null, [], false, false, false, []);
        $parameters = $this->createMock(WriteParameterBag::class);

        $result = iterator_to_array($serializer->encode($field, $existence, $data, $parameters));

        static::assertArrayHasKey('test', $result);
        static::assertSame($expected, $result['test']);
    }

    /**
     * @return iterable<string, array{value: mixed, expected: string|null}>
     */
    public static function stripTagsProvider(): iterable
    {
        yield 'simple text without tags' => [
            'value' => 'Hello World',
            'expected' => 'Hello World',
        ];

        yield 'text with HTML tags' => [
            'value' => 'Hello <b>World</b>',
            'expected' => 'Hello World',
        ];

        yield 'text with multiple tags' => [
            'value' => '<p>Hello <strong>beautiful</strong> <em>World</em></p>',
            'expected' => 'Hello beautiful World',
        ];

        yield 'text with script tag' => [
            'value' => 'Hello <script>alert("xss")</script>World',
            'expected' => 'Hello alert("xss")World',
        ];

        yield 'text with special characters like <3' => [
            'value' => 'I <3 cats',
            'expected' => 'I <3 cats',
        ];
        yield 'more complex case' => [
            'value' => 'product <script>alert("go");</script> <5g -> product <5g',
            'expected' => 'product alert("go"); <5g -> product <5g',
        ];

        yield 'text with self-closing tags' => [
            'value' => 'Line 1<br/>Line 2',
            'expected' => 'Line 1Line 2',
        ];

        yield 'text with attributes in tags' => [
            'value' => '<a href="https://www.shopware.com/">Link</a>',
            'expected' => 'Link',
        ];

        yield 'empty string' => [
            'value' => '',
            'expected' => '',
        ];

        yield 'only tags no content' => [
            'value' => '<div></div>',
            'expected' => '',
        ];

        yield 'nested tags' => [
            'value' => '<div><span><b>Bold</b></span></div>',
            'expected' => 'Bold',
        ];

        yield 'malformed HTML' => [
            'value' => '<div>Unclosed <b>tag',
            'expected' => 'Unclosed tag',
        ];

        yield 'numeric value' => [
            'value' => 12345,
            'expected' => '12345',
        ];

        yield 'null value' => [
            'value' => null,
            'expected' => null,
        ];

        yield 'whitespace only' => [
            'value' => '   ',
            'expected' => '   ',
        ];

        yield 'text with leading/trailing whitespace' => [
            'value' => '  Hello World  ',
            'expected' => '  Hello World  ',
        ];
    }
}
