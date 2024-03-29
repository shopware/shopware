<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Flow\FlowAction\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Flow\Action\Action;

/**
 * @internal
 */
class MetadataTest extends TestCase
{
    public function testFromXml(): void
    {
        $flowActionsFile = '/../_fixtures/valid/major/flow.xml';
        $flowActions = Action::createFromXmlFile(__DIR__ . $flowActionsFile);

        static::assertNotNull($flowActions->getActions());
        static::assertCount(1, $flowActions->getActions()->getActions());

        $firstAction = $flowActions->getActions()->getActions()[0];
        $meta = $firstAction->getMeta();

        static::assertSame('abc.cde.ccc', $meta->getName());
        static::assertSame(['order', 'customer'], $meta->getRequirements());
        static::assertSame('https://example.xyz', $meta->getUrl());
        static::assertSame('sw-pencil', $meta->getSwIcon());
        static::assertSame('resource/pencil', $meta->getIcon());
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
