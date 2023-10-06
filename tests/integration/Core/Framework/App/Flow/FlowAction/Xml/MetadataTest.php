<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Flow\FlowAction\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Flow\Action\Action;
use Shopware\Core\Framework\Feature;

/**
 * @internal
 */
class MetadataTest extends TestCase
{
    public function testFromXml(): void
    {
        $flowActionsFile = Feature::isActive('v6.6.0.0') ? '/../_fixtures/valid/major/flow.xml' : '/../_fixtures/valid/minor/flow-action.xml';
        $flowActions = Action::createFromXmlFile(__DIR__ . $flowActionsFile);

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
