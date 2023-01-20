<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\FlowAction\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\FlowAction\FlowAction;

/**
 * @internal
 */
class ActionTest extends TestCase
{
    public function testFromXml(): void
    {
        $flowActions = FlowAction::createFromXmlFile(__DIR__ . '/../_fixtures/valid/flowActionWithFlowActions.xml');

        static::assertNotNull($flowActions->getActions());
        static::assertCount(1, $flowActions->getActions()->getActions());

        $firstAction = $flowActions->getActions()->getActions()[0];
        static::assertNotNull($firstAction->getMeta());
        static::assertNotNull($firstAction->getHeaders());
        static::assertNotNull($firstAction->getParameters());
        static::assertNotNull($firstAction->getConfig());

        static::assertEquals('abc.cde.ccc', $firstAction->getMeta()->getName());
        static::assertEquals(['order', 'customer'], $firstAction->getMeta()->getRequirements());
        static::assertEquals(
            [
                'en-GB' => 'First action app',
                'de-DE' => 'First action app DE',
            ],
            $firstAction->getMeta()->getLabel()
        );
    }
}
