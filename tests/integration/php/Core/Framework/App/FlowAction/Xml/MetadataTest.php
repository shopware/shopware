<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\FlowAction\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\FlowAction\FlowAction;

/**
 * @internal
 */
class MetadataTest extends TestCase
{
    public function testFromXml(): void
    {
        $flowActions = FlowAction::createFromXmlFile(__DIR__ . '/../_fixtures/valid/flowActionWithFlowActions.xml');

        static::assertNotNull($flowActions->getActions());
        static::assertCount(1, $flowActions->getActions()->getActions());

        $firstAction = $flowActions->getActions()->getActions()[0];
        $meta = $firstAction->getMeta();

        static::assertEquals('abc.cde.ccc', $meta->getName());
        static::assertEquals(['order', 'customer'], $meta->getRequirements());
        static::assertEquals('https://example.xyz', $meta->getUrl());
        static::assertEquals('sw-pencil', $meta->getSwIcon());
        static::assertEquals('resource/pencil', $meta->getIcon());
        static::assertEquals(
            [
                'en-GB' => 'First action app',
                'de-DE' => 'First action app DE',
            ],
            $firstAction->getMeta()->getLabel()
        );
        static::assertEquals(
            [
                'en-GB' => 'First action app description',
                'de-DE' => 'First action app description DE',
            ],
            $firstAction->getMeta()->getDescription()
        );
        static::assertEquals(
            [
                'en-GB' => 'Headline for action',
                'de-DE' => 'Überschrift für Aktion',
            ],
            $firstAction->getMeta()->getHeadline()
        );
    }
}
