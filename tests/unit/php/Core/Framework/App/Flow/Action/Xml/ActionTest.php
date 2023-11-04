<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Flow\Action\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Flow\Action\Xml\Action;
use Shopware\Core\Framework\App\Flow\Action\Xml\Config;
use Shopware\Core\Framework\App\Flow\Action\Xml\Headers;
use Shopware\Core\Framework\App\Flow\Action\Xml\InputField;
use Shopware\Core\Framework\App\Flow\Action\Xml\Metadata;
use Shopware\Core\Framework\App\Flow\Action\Xml\Parameter;
use Shopware\Core\Framework\App\Flow\Action\Xml\Parameters;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\App\Flow\Action\Xml\Action
 */
class ActionTest extends TestCase
{
    private Parameters $parameters;

    private Config $config;

    private Headers $headers;

    private Action $action;

    private \DOMDocument $document;

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
        /** @var \DOMElement $meta */
        $meta = $action->getElementsByTagName('meta')->item(0);

        $meta = Metadata::fromXml($meta);

        $parameter = new Parameter(['id' => 'key']);
        $this->parameters = new Parameters([$parameter]);
        $this->headers = new Headers([$parameter]);
        $inputFiled = new InputField(['id' => 'key']);
        $this->config = new Config([$inputFiled]);

        $this->action = new Action([
            'meta' => $meta,
            'headers' => $this->headers,
            'parameters' => $this->parameters,
            'config' => $this->config,
        ]);
    }

    public function testToArray(): void
    {
        $result = $this->action->toArray('en-GB');
        static::assertArrayHasKey('name', $result);
        static::assertArrayHasKey('swIcon', $result);
        static::assertArrayHasKey('url', $result);
        static::assertArrayHasKey('delayable', $result);
        static::assertArrayHasKey('parameters', $result);
        static::assertArrayHasKey('config', $result);
        static::assertArrayHasKey('headers', $result);
        static::assertArrayHasKey('requirements', $result);
        static::assertArrayHasKey('label', $result);
        static::assertArrayHasKey('description', $result);
        static::assertArrayHasKey('headline', $result);
    }

    public function testFromXml(): void
    {
        /** @var \DOMElement $actions */
        $actions = $this->document->getElementsByTagName('flow-actions')->item(0);
        foreach ($actions->getElementsByTagName('flow-action') as $action) {
            $result = $this->action->fromXml($action)->toArray('en-GB');
            static::assertArrayHasKey('meta', $result);
            static::assertArrayHasKey('headers', $result);
            static::assertArrayHasKey('config', $result);
            static::assertArrayHasKey('parameters', $result);
        }
    }

    public function testGetMeta(): void
    {
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

        static::assertEquals($expected, $this->action->getMeta()->toArray('en-GB'));
    }

    public function testGetHeaders(): void
    {
        static::assertArrayHasKey('parameters', $this->action->getHeaders()->toArray('eb-GB'));
    }

    public function testGetParameters(): void
    {
        static::assertArrayHasKey('parameters', $this->action->getParameters()->toArray('eb-GB'));
    }

    public function testGetConfig(): void
    {
        static::assertArrayHasKey('config', $this->action->getConfig()->toArray('eb-GB'));
    }
}
