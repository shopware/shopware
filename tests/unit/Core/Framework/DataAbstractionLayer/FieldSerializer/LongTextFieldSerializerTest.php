<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowEmptyString;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Flag;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\LongTextFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LongTextFieldSerializer::class)]
class LongTextFieldSerializerTest extends TestCase
{
    private LongTextFieldSerializer $serializer;

    protected function setUp(): void
    {
        $definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);

        $sanitizer = new HtmlSanitizer(null, true, $this->getHtmlSanitizerSets(), $this->getHtmlSanitizerFieldSets());
        $this->serializer = new LongTextFieldSerializer(Validation::createValidator(), $definitionRegistry, $sanitizer);
    }

    public function testEncodeThrowExceptionOnWrongField(): void
    {
        $field = new ManyToOneAssociationField('test', 'test', 'test');
        $existence = new EntityExistence('test', [], true, false, false, []);
        $keyPair = new KeyValuePair('longText', null, false);
        $bag = new WriteParameterBag(
            new ProductDefinition(),
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue()
        );

        static::expectException(DataAbstractionLayerException::class);
        static::expectExceptionMessage(DataAbstractionLayerException::invalidSerializerField(LongTextField::class, $field)->getMessage());

        iterator_to_array($this->serializer->encode($field, $existence, $keyPair, $bag));
    }

    #[DataProvider('encodeProvider')]
    public function testEncode(?string $text, ?string $expectedValue): void
    {
        $field = (new LongTextField('long_text', 'longText'))->addFlags(new AllowHtml());
        $existence = new EntityExistence('test', [], false, false, false, []);
        $keyPair = new KeyValuePair('longText', $text, false);
        $bag = new WriteParameterBag(
            new ProductDefinition(),
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue()
        );

        $result = iterator_to_array($this->serializer->encode($field, $existence, $keyPair, $bag));

        static::assertArrayHasKey('long_text', $result);
        static::assertSame($expectedValue, $result['long_text']);
    }

    public static function encodeProvider(): \Generator
    {
        yield 'Long text with null value' => [
            'text' => null,
            'expectedValue' => null,
        ];

        yield 'Long text with only invalid tag' => [
            'text' => '<ul class="list-default-style"></ul>',
            'expectedValue' => null,
        ];

        yield 'Long text with invalid tag' => [
            'text' => 'Some text<ul class="list-default-style"></ul>',
            'expectedValue' => 'Some text',
        ];

        yield 'Long text with valid tag' => [
            'text' => '<ul class="list-default-style"><li></li></ul>',
            'expectedValue' => '<ul class="list-default-style"><li></li></ul>',
        ];
    }

    /**
     * @param list<Flag> $flags
     */
    #[DataProvider('validationProvider')]
    public function testEncodeValidatesRequiredAndEmptyValues(bool|string|null $input, ?string $expected, bool $expectError, array $flags = []): void
    {
        $field = new LongTextField('long_text', 'longText');
        $field->addFlags(...$flags);
        $keyPair = new KeyValuePair('longText', $input, false);
        $bag = new WriteParameterBag(
            new ProductDefinition(),
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue()
        );

        try {
            $result = iterator_to_array($this->serializer->encode($field, EntityExistence::createEmpty(), $keyPair, $bag));
        } catch (WriteConstraintViolationException $exception) {
            static::assertTrue($expectError);
            static::assertSame('/longText', $exception->getViolations()->get(0)->getPropertyPath());

            return;
        }

        static::assertFalse($expectError);
        static::assertSame(['long_text' => $expected], $result);
    }

    /**
     * @return array<string, array{bool|string|null, ?string, bool, 3?: list<Flag>}>
     */
    public static function validationProvider(): array
    {
        return [
            'required HTML-only content throws after tag stripping' => ['<test>', null, true, [new Required()]],
            'required null content throws' => [null, null, true, [new Required()]],
            'required empty content throws' => ['', null, true, [new Required()]],
            'wrong type throws' => [true, null, true, [new Required()]],
            'required and allow empty throws with null' => [null, null, true, [new Required(), new AllowEmptyString()]],
            'string values are passed through' => ['test12-B', 'test12-B', false, [new Required()]],
            'null is allowed without required flag' => [null, null, false],
            'sanitation can be turned off' => ['<test>', '<test>', false, [new Required(), new AllowHtml(false)]],
            'empty string is treated as null without allow empty flag' => ['', null, false],
            'empty string is passed through with allow empty flag' => ['', '', false, [new AllowEmptyString()]],
            'empty string is allowed with required and allow empty flags' => ['', '', false, [new Required(), new AllowEmptyString()]],
            'HTML content is sanitized' => ['<script></script>test12-B', 'test12-B', false, [new Required(), new AllowHtml()]],
        ];
    }

    public function testDecodeThrowExceptionOnWrongField(): void
    {
        $field = new LongTextField('test', 'test');

        static::expectException(DataAbstractionLayerException::class);
        static::expectExceptionMessage(DataAbstractionLayerException::invalidArraySerialization($field, [])->getMessage());

        $this->serializer->decode($field, []);
    }

    #[DataProvider('decodeProvider')]
    public function testDecode(?string $value, ?string $expectResult): void
    {
        $field = new LongTextField('test', 'test');

        $decoded = $this->serializer->decode($field, $value);

        static::assertSame($expectResult, $decoded);
    }

    public static function decodeProvider(): \Generator
    {
        yield 'Long text with null value' => [
            'value' => null,
            'expectResult' => null,
        ];

        yield 'Long text with empty value' => [
            'value' => '',
            'expectResult' => '',
        ];

        yield 'Long text with string' => [
            'value' => 'Some text',
            'expectResult' => 'Some text',
        ];
    }

    /**
     * @return array<string, array{sets?: list<string>|null}>
     */
    private function getHtmlSanitizerFieldSets(): array
    {
        return [
            'product_translation.description' => [
                'set' => ['basic', 'media', 'HTML5'],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getHtmlSanitizerSets(): array
    {
        return [
            'HTML5' => [
                'tags' => [
                    'article', 'aside', 'audio', 'bdi', 'canvas', 'datalist', 'details', 'dialog', 'embed', 'figcaption', 'figure',
                    'footer', 'header', 'main', 'mark', 'meter', 'nav', 'progress', 'rp', 'rt', 'ruby', 'section', 'summary',
                    'time', 'wbr', 'output', 'canvas', 'svg', 'track', 'video', 'source', 'input',
                ],
                'attributes' => [
                    'controls', 'open', 'min', 'max', 'datetime', 'for', 'type', 'kind', 'srclang', 'label', 'value', 'placeholder',
                    'autoplay', 'loop', 'muted', 'preload', 'low', 'high', 'optimum', 'default', 'poster', 'media', 'maxlength',
                    'minlength', 'pattern', 'required', 'autocomplete', 'autofocus', 'disabled', 'readonly', 'multiple', 'formaction',
                    'formenctype', 'formmethod', 'formnovalidate', 'formtarget', 'list', 'step', 'checked', 'accept',
                ],
                'custom_attributes' => [],
                'options' => [],
            ],
            'basic' => [
                'tags' => [
                    'a', 'abbr', 'acronym', 'address', 'b', 'bdo', 'big', 'blockquote', 'br', 'caption', 'center', 'cite', 'code',
                    'col', 'colgroup', 'dd', 'del', 'dfn', 'dir', 'div', 'dl', 'dt', 'em', 'font', 'h1', 'h2', 'h3', 'h4', 'h5',
                    'h6', 'hr', 'i', 'ins', 'kbd', 'li', 'menu', 'ol', 'p', 'pre', 'q', 's', 'samp', 'small', 'span', 'strike',
                    'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'tt', 'u', 'ul', 'var', 'img',
                ],
                'attributes' => [
                    'align', 'bgcolor', 'border', 'cellpadding', 'cellspacing', 'cite', 'class', 'clear', 'color', 'colspan', 'dir',
                    'face', 'frame', 'height', 'href', 'id', 'lang', 'name', 'noshade', 'nowrap', 'rel', 'rev', 'rowspan', 'scope',
                    'size', 'span', 'start', 'style', 'summary', 'title', 'type', 'valign', 'value', 'width', 'target', 'src', 'alt',
                ],
                'options' => [
                    'Attr.AllowedFrameTargets' => [
                        'values' => ['_blank', '_self', '_parent', '_top'],
                    ],
                    'Attr.AllowedRel' => [
                        'values' => ['nofollow', 'print'],
                    ],
                    'Attr.EnableID' => true,
                ],
                'custom_attributes' => [],
            ],
            'media' => [
                'tags' => ['img'],
                'attributes' => ['src', 'alt'],
                'custom_attributes' => [],
                'options' => [],
            ],
        ];
    }
}
