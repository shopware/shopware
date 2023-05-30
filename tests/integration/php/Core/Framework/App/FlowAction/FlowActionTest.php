<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\FlowAction;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\FlowAction\FlowAction;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;

/**
 * @internal
 */
class FlowActionTest extends TestCase
{
    public function testCreateFromXmlWithFlowAction(): void
    {
        $flowActions = FlowAction::createFromXmlFile(__DIR__ . '/_fixtures/valid/flowActionWithFlowActions.xml');

        static::assertEquals(__DIR__ . '/_fixtures/valid', $flowActions->getPath());
        static::assertNotNull($flowActions->getActions());
        static::assertCount(1, $flowActions->getActions()->getActions());
    }

    public function testCreateFromXmlMissingFlowAction(): void
    {
        static::expectException(XmlParsingException::class);
        static::expectExceptionMessage('[ERROR 1871] Element \'flow-actions\': Missing child element(s). Expected is ( flow-action ).');
        FlowAction::createFromXmlFile(__DIR__ . '/_fixtures/invalid/flowActionsWithoutFlowAction.xml');
    }

    public function testCreateFromXmlFlowActionMissingRequiredChild(): void
    {
        static::expectException(XmlParsingException::class);
        static::expectExceptionMessage('[ERROR 1871] Element \'flow-action\': Missing child element(s). Expected is one of ( headers, parameters, config ).');
        FlowAction::createFromXmlFile(__DIR__ . '/_fixtures/invalid/flowActionWithoutRequiredChild.xml');
    }

    public function testCreateFromXmlFlowActionConfigMissingRequiredChild(): void
    {
        static::expectException(XmlParsingException::class);
        static::expectExceptionMessage('Message: [ERROR 1871] Element \'config\': Missing child element(s). Expected is ( input-field ).');
        FlowAction::createFromXmlFile(__DIR__ . '/_fixtures/invalid/flowActionConfigWithoutRequiredChild.xml');
    }

    public function testCreateFromXmlFlowActionConfigInputFieldTypeInvalid(): void
    {
        static::expectException(XmlParsingException::class);
        static::expectExceptionMessage('[ERROR 1840] Element \'input-field\', attribute \'type\': [facet \'enumeration\'] The value \'shopware\' is not an element of the set {\'text\', \'textarea\', \'text-editor\', \'url\', \'password\', \'int\', \'float\', \'bool\', \'checkbox\', \'datetime\', \'date\', \'time\', \'colorpicker\', \'single-select\', \'multi-select\'}.');
        FlowAction::createFromXmlFile(__DIR__ . '/_fixtures/invalid/flowActionInputFieldType.xml');
    }
}
