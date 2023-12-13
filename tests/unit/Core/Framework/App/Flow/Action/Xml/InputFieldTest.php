<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Flow\Action\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Flow\Action\Xml\InputField;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 */
#[CoversClass(InputField::class)]
class InputFieldTest extends TestCase
{
    private \DOMElement $config;

    protected function setUp(): void
    {
        $document = XmlUtils::loadFile(
            __DIR__ . '/../../../_fixtures/Resources/flow.xml',
            __DIR__ . '/../../../../../../../../src/Core/Framework/App/Flow/Schema/flow-1.0.xsd'
        );
        $actions = $document->getElementsByTagName('flow-actions')->item(0);
        static::assertNotNull($actions);
        $action = $actions->getElementsByTagName('flow-action')->item(0);
        static::assertNotNull($action);

        $config = $action->getElementsByTagName('config')->item(0);
        static::assertNotNull($config);
        $this->config = $config;
    }

    public function testFromXml(): void
    {
        $inputField = $this->config->getElementsByTagName('input-field')->item(0);
        static::assertNotNull($inputField);

        $expectedInputField = InputField::fromArray([
            'name' => 'textField',
            'label' => [
                'en-GB' => 'To',
                'de-DE' => 'To DE',
            ],
            'required' => true,
            'defaultValue' => 'Shopware 6',
            'placeHolder' => [
                'en-GB' => 'Enter to...',
                'de-DE' => 'Enter to DE...',
            ],
            'type' => 'text',
            'helpText' => [
                'en-GB' => 'Help text',
                'de-DE' => 'Help text DE',
            ],
        ]);

        $inputFieldResult = InputField::fromXml($inputField);
        static::assertEquals($expectedInputField, $inputFieldResult);
    }

    public function testFromXmlWithOption(): void
    {
        $inputField = $this->config->getElementsByTagName('input-field')->item(3);
        static::assertNotNull($inputField);

        $expectedInputField = InputField::fromArray([
            'name' => 'mailMethod',
            'label' => null,
            'required' => null,
            'defaultValue' => null,
            'placeHolder' => null,
            'type' => 'single-select',
            'helpText' => null,
            'options' => [
                [
                    'value' => 'smtp',
                    'label' => [
                        'en-GB' => 'English label',
                        'de-DE' => 'German label',
                    ],
                ],
                [
                    'value' => 'pop3',
                    'label' => [
                        'en-GB' => 'English label',
                        'de-DE' => 'German label',
                    ],
                ],
            ],
        ]);

        $inputFieldResult = InputField::fromXml($inputField);
        static::assertEquals($expectedInputField, $inputFieldResult);
    }

    public function testToArray(): void
    {
        $inputField = InputField::fromArray([
            'name' => 'textField',
            'label' => [
                'en-GB' => 'To',
                'de-DE' => 'To DE',
            ],
            'required' => true,
            'defaultValue' => 'Shopware 6',
            'placeHolder' => [
                'en-GB' => 'Enter to...',
                'de-DE' => 'Enter to DE...',
            ],
            'type' => 'text',
            'helpText' => [
                'en-GB' => 'Help text',
                'de-DE' => 'Help text DE',
            ],
        ]);

        $result = $inputField->toArray('en-GB');
        static::assertArrayHasKey('name', $result);
        static::assertArrayHasKey('label', $result);
        static::assertArrayHasKey('placeHolder', $result);
        static::assertArrayHasKey('required', $result);
        static::assertArrayHasKey('helpText', $result);
        static::assertArrayHasKey('defaultValue', $result);
        static::assertArrayHasKey('options', $result);
        static::assertArrayHasKey('type', $result);
    }
}
