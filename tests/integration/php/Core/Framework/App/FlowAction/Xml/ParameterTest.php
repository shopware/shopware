<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\FlowAction\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\FlowAction\FlowAction;

/**
 * @internal
 */
class ParameterTest extends TestCase
{
    public function testFromXml(): void
    {
        $flowActions = FlowAction::createFromXmlFile(__DIR__ . '/../_fixtures/valid/flowActionWithFlowActions.xml');

        static::assertNotNull($flowActions->getActions());
        static::assertCount(1, $flowActions->getActions()->getActions());

        $firstAction = $flowActions->getActions()->getActions()[0];
        $firstHeaderParameter = $firstAction->getHeaders()->getParameters()[0];
        $firstParameter = $firstAction->getParameters()->getParameters()[0];

        static::assertEquals('string', $firstHeaderParameter->getType());
        static::assertEquals('content-type', $firstHeaderParameter->getName());
        static::assertEquals('application/json', $firstHeaderParameter->getValue());

        static::assertEquals('string', $firstParameter->getType());
        static::assertEquals('to', $firstParameter->getName());
        static::assertEquals('{{ customer.name }}', $firstParameter->getValue());
    }
}
