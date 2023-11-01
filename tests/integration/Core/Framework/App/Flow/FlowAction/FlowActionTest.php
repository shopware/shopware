<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Flow\FlowAction;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Flow\Action\Action;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;

/**
 * @internal
 */
class FlowActionTest extends TestCase
{
    public function testCreateFromXmlWithFlowAction(): void
    {
        $flowActionsFile = Feature::isActive('v6.6.0.0') ? '/_fixtures/valid/major/flow.xml' : '/_fixtures/valid/minor/flow-action.xml';
        $flowActions = Action::createFromXmlFile(__DIR__ . $flowActionsFile);

        if (Feature::isActive('v6.6.0.0')) {
            static::assertSame(__DIR__ . '/_fixtures/valid/major', $flowActions->getPath());
        } else {
            static::assertSame(__DIR__ . '/_fixtures/valid/minor', $flowActions->getPath());
        }

        static::assertNotNull($flowActions->getActions());
        static::assertCount(1, $flowActions->getActions()->getActions());
    }

    public function testCreateFromXmlMissingFlowAction(): void
    {
        static::expectException(XmlParsingException::class);
        static::expectExceptionMessage('[ERROR 1871] Element \'flow-actions\': Missing child element(s). Expected is ( flow-action ).');
        $flowActionsFile = Feature::isActive('v6.6.0.0') ? '/_fixtures/invalid/FlowActionsWithoutFlowActionMajor/flow.xml' : '/_fixtures/invalid/FlowActionsWithoutFlowAction/flow-action.xml';
        Action::createFromXmlFile(__DIR__ . $flowActionsFile);
    }

    public function testCreateFromXmlFlowActionMissingRequiredChild(): void
    {
        static::expectException(XmlParsingException::class);
        static::expectExceptionMessage('[ERROR 1871] Element \'flow-action\': Missing child element(s). Expected is one of ( headers, parameters, config ).');
        $flowActionsFile = Feature::isActive('v6.6.0.0') ? '/_fixtures/invalid/FlowActionWithoutRequiredChildMajor/flow.xml' : '/_fixtures/invalid/FlowActionWithoutRequiredChild/flow-action.xml';
        Action::createFromXmlFile(__DIR__ . $flowActionsFile);
    }

    public function testCreateFromXmlFlowActionConfigMissingRequiredChild(): void
    {
        static::expectException(XmlParsingException::class);
        static::expectExceptionMessage('Message: [ERROR 1871] Element \'config\': Missing child element(s). Expected is ( input-field ).');
        $flowActionsFile = Feature::isActive('v6.6.0.0') ? '/_fixtures/invalid/FlowActionConfigWithoutRequiredChildMajor/flow.xml' : '/_fixtures/invalid/FlowActionConfigWithoutRequiredChild/flow-action.xml';
        Action::createFromXmlFile(__DIR__ . $flowActionsFile);
    }

    public function testCreateFromXmlFlowActionConfigInputFieldTypeInvalid(): void
    {
        static::expectException(XmlParsingException::class);
        static::expectExceptionMessage('[ERROR 1840] Element \'input-field\', attribute \'type\': [facet \'enumeration\'] The value \'shopware\' is not an element of the set {\'text\', \'textarea\', \'text-editor\', \'url\', \'password\', \'int\', \'float\', \'bool\', \'checkbox\', \'datetime\', \'date\', \'time\', \'colorpicker\', \'single-select\', \'multi-select\'}.');
        $flowActionsFile = Feature::isActive('v6.6.0.0') ? '/_fixtures/invalid/FlowActionInputFieldTypeMajor/flow.xml' : '/_fixtures/invalid/FlowActionInputFieldType/flow-action.xml';
        Action::createFromXmlFile(__DIR__ . $flowActionsFile);
    }
}
