<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Flow\Action\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Flow\Action\Xml\InputField;

/**
 * @internal
 */
#[CoversClass(InputField::class)]
class InputFieldTest extends TestCase
{
    public function testFromXml(): void
    {
        $inputField = InputField::fromXml(self::loadElement(<<<'XML'
<input-field>
    <name>message</name>
    <label>Message</label>
    <label lang="de-DE">Nachricht</label>
    <place-holder>Enter message...</place-holder>
    <place-holder lang="de-DE">Nachricht eingeben...</place-holder>
    <helpText>Visible to customers</helpText>
    <helpText lang="de-DE">Fuer Kunden sichtbar</helpText>
    <required>true</required>
    <defaultValue>Hello</defaultValue>
</input-field>
XML));

        static::assertSame('message', $inputField->getName());
        static::assertSame(
            [
                'en-GB' => 'Message',
                'de-DE' => 'Nachricht',
            ],
            $inputField->getLabel()
        );
        static::assertSame(
            [
                'en-GB' => 'Enter message...',
                'de-DE' => 'Nachricht eingeben...',
            ],
            $inputField->getPlaceHolder()
        );
        static::assertSame(
            [
                'en-GB' => 'Visible to customers',
                'de-DE' => 'Fuer Kunden sichtbar',
            ],
            $inputField->getHelpText()
        );
        static::assertTrue($inputField->getRequired());
        static::assertSame('Hello', $inputField->getDefaultValue());
        static::assertSame('text', $inputField->getType());
        static::assertSame([], $inputField->getOptions());
    }

    public function testFromXmlWithOptions(): void
    {
        $inputField = InputField::fromXml(self::loadElement(<<<'XML'
<input-field type="single-select">
    <name>mailMethod</name>
    <options>
        <option value="smtp">
            <label>SMTP</label>
            <label lang="de-DE">SMTP DE</label>
        </option>
        <option value="pop3">
            <label>POP3</label>
        </option>
    </options>
</input-field>
XML));

        static::assertSame('mailMethod', $inputField->getName());
        static::assertSame('single-select', $inputField->getType());
        static::assertSame(
            [
                [
                    'value' => 'smtp',
                    'label' => [
                        'en-GB' => 'SMTP',
                        'de-DE' => 'SMTP DE',
                    ],
                ],
                [
                    'value' => 'pop3',
                    'label' => [
                        'en-GB' => 'POP3',
                    ],
                ],
            ],
            $inputField->getOptions()
        );
    }

    public function testToArray(): void
    {
        $inputField = InputField::fromXml(self::loadElement(<<<'XML'
<input-field type="text">
    <name>message</name>
    <label>Message</label>
    <required>false</required>
    <defaultValue>Hello</defaultValue>
</input-field>
XML));

        static::assertSame(
            [
                'name' => 'message',
                'label' => ['en-GB' => 'Message'],
                'placeHolder' => null,
                'required' => false,
                'helpText' => null,
                'defaultValue' => 'Hello',
                'options' => [],
                'type' => 'text',
            ],
            $inputField->toArray('en-GB')
        );
    }

    private static function loadElement(string $xml): \DOMElement
    {
        $document = new \DOMDocument();
        static::assertTrue($document->loadXML($xml));
        static::assertInstanceOf(\DOMElement::class, $document->documentElement);

        return $document->documentElement;
    }
}
