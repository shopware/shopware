<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Flow\Action\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Flow\Action\Xml\Metadata;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\App\Flow\Action\Xml\Metadata
 */
class MetadataTest extends TestCase
{
    public function testFromXml(): void
    {
        $document = XmlUtils::loadFile(
            __DIR__ . '/../../../_fixtures/Resources/flow-action.xml',
            __DIR__ . '/../../../../../../../../src/Core/Framework/App/FlowAction/Schema/flow-action-1.0.xsd'
        );
        /** @var \DOMElement $actions */
        $actions = $document->getElementsByTagName('flow-actions')->item(0);
        /** @var \DOMElement $action */
        $action = $actions->getElementsByTagName('flow-action')->item(0);

        /** @var \DOMElement $meta */
        $meta = $action->getElementsByTagName('meta')->item(0);

        $expected = [
            'label' => [
                'en-GB' => 'First action app',
                'de-DE' => 'First action app DE',
            ],
            'description' => [
                'en-GB' => 'First action app description',
                'de-DE' => 'First action app description DE',
            ],
            'name' => 'abc.cde.ccc',
            'url' => 'https://example.xyz',
            'requirements' => ['order', 'customer'],
            'icon' => 'resource/pencil',
            'swIcon' => 'sw-pencil',
            'headline' => [
                'en-GB' => 'Headline for action',
                'de-DE' => 'Überschrift für Aktion',
            ],
            'delayable' => true,
            'badge' => 'abc',
        ];

        $metaData = Metadata::fromXml($meta);
        static::assertEquals($expected, $metaData->toArray('en-GB'));
    }
}
