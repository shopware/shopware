<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\FlowAction\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\FlowAction\FlowAction;
use Shopware\Core\Framework\App\FlowAction\Xml\InputField;

class InputFieldTest extends TestCase
{
    public function testFromXml(): void
    {
        $flowActions = FlowAction::createFromXmlFile(__DIR__ . '/../_fixtures/valid/flowActionWithFlowActions.xml');
        static::assertCount(1, $flowActions->getActions()->getActions());
        $config = $flowActions->getActions()->getActions()[0]->getConfig()->getConfig();
        static::assertCount(5, $config);
        /**
         * @var InputField $firstInputField
         */
        $firstInputField = $config[0];

        static::assertEquals('textField', $firstInputField->getName());
        static::assertEquals('text', $firstInputField->getType());
        static::assertEquals([
            'en-GB' => 'To',
            'de-DE' => 'To DE',
        ], $firstInputField->getLabel());
        static::assertEquals([
            'en-GB' => 'Enter to...',
            'de-DE' => 'Enter to DE...',
        ], $firstInputField->getPlaceHolder());
        static::assertEquals([
            'en-GB' => 'Help text',
            'de-DE' => 'Help text DE',
        ], $firstInputField->getHelpText());

        static::assertTrue($firstInputField->getRequired());
        static::assertTrue($firstInputField->getDisabled());
        static::assertFalse($firstInputField->getEditable());
        static::assertEquals('Shopware 6', $firstInputField->getDefaultValue());
    }
}
