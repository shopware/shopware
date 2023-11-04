<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Flow\Action\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Flow\Action\Xml\InputField;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\App\Flow\Action\Xml\InputField
 */
class InputFieldTest extends TestCase
{
    private \DOMDocument $document;

    /**
     * @var \DOMElement|null
     */
    private $config;

    protected function setUp(): void
    {
        $this->document = XmlUtils::loadFile(
            __DIR__ . '/../../../_fixtures/Resources/flow-action.xml',
            __DIR__ . '/../../../../../../../../../src/Core/Framework/App/FlowAction/Schema/flow-action-1.0.xsd'
        );
        /** @var \DOMElement $actions */
        $actions = $this->document->getElementsByTagName('flow-actions')->item(0);
        /** @var \DOMElement $action */
        $action = $actions->getElementsByTagName('flow-action')->item(0);

        $this->config = $action->getElementsByTagName('config')->item(0);
    }

    public function testFromXml(): void
    {
        static::assertNotNull($this->config);
        /** @var \DOMElement $inputField */
        $inputField = $this->config->getElementsByTagName('input-field')->item(0);

        $expectedInputField = new InputField([
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
        static::assertNotNull($this->config);
        /** @var \DOMElement $inputField */
        $inputField = $this->config->getElementsByTagName('input-field')->item(3);

        $expectedInputField = new InputField([
            'name' => 'mailMethod',
            'label' => null,
            'required' => null,
            'defaultValue' => null,
            'placeHolder' => null,
            'type' => 'single-select',
            'helpText' => [],
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
        $inputField = new InputField([
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
