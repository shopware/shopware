<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\FlowAction\Xml;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\FlowAction\Xml\Action;
use Shopware\Core\Framework\App\FlowAction\Xml\Config;
use Shopware\Core\Framework\App\FlowAction\Xml\Headers;
use Shopware\Core\Framework\App\FlowAction\Xml\InputField;
use Shopware\Core\Framework\App\FlowAction\Xml\Metadata;
use Shopware\Core\Framework\App\FlowAction\Xml\Parameter;
use Shopware\Core\Framework\App\FlowAction\Xml\Parameters;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\App\FlowAction\Xml\Action
 */
class ActionTest extends TestCase
{
    private MockObject&Parameters $parameters;

    private MockObject&Config $config;

    private MockObject&Headers $headers;

    private Action $action;

    public function setUp(): void
    {
        /** @var Metadata $meta */
        $meta = $this->createMock(Metadata::class);

        $this->headers = $this->createMock(Headers::class);
        $this->parameters = $this->createMock(Parameters::class);
        $this->config = $this->createMock(Config::class);
        $this->action = new Action([
            'meta' => $meta,
            'headers' => $this->headers,
            'parameters' => $this->parameters,
            'config' => $this->config,
        ]);
    }

    public function testToArray(): void
    {
        $this->parameters->expects(static::once())
            ->method('getParameters')
            ->willReturn(
                [$this->createMock(Parameter::class)]
            );

        $this->config->expects(static::once())
            ->method('getConfig')
            ->willReturn(
                [$this->createMock(InputField::class)]
            );

        $this->headers->expects(static::once())
            ->method('getParameters')
            ->willReturn(
                [$this->createMock(Parameter::class)]
            );

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
        $doc = XmlUtils::loadFile(
            __DIR__ . '/../../_fixtures/flow-actions.xml',
            __DIR__ . '/../../../../../../../../src/Core/Framework/App/FlowAction/Schema/flow-action-1.0.xsd'
        );

        /** @var \DOMElement $actions */
        $actions = $doc->getElementsByTagName('flow-actions')->item(0);
        foreach ($actions->getElementsByTagName('flow-action') as $action) {
            $result = $this->action::fromXml($action);
            static::assertInstanceOf(Action::class, $result);
        }
    }

    public function testGetMeta(): void
    {
        static::assertInstanceOf(Metadata::class, $this->action->getMeta());
    }

    public function testGetHeaders(): void
    {
        static::assertInstanceOf(Headers::class, $this->action->getHeaders());
    }

    public function testGetParameters(): void
    {
        static::assertInstanceOf(Parameters::class, $this->action->getParameters());
    }

    public function testGetConfig(): void
    {
        static::assertInstanceOf(Config::class, $this->action->getConfig());
    }
}
