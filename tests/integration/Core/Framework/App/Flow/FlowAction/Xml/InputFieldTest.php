<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Flow\FlowAction\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Flow\Action\Action;

/**
 * @internal
 */
class InputFieldTest extends TestCase
{
    public function testFromXml(): void
    {
        $flowActionsFile = '/../_fixtures/valid/major/flow.xml';
        $flowActions = Action::createFromXmlFile(__DIR__ . $flowActionsFile);
        static::assertNotNull($flowActions->getActions());
        static::assertCount(1, $flowActions->getActions()->getActions());
        $config = $flowActions->getActions()->getActions()[0]->getConfig()->getConfig();
        static::assertCount(4, $config);

        $firstInputField = $config[0];

        static::assertSame('textField', $firstInputField->getName());
        static::assertSame('text', $firstInputField->getType());
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
        static::assertSame('Shopware 6', $firstInputField->getDefaultValue());
    }
}
