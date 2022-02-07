<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\FlowAction\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\FlowAction\FlowAction;
use Shopware\Core\Framework\App\FlowAction\Xml\Component;

class ComponentTest extends TestCase
{
    public function testFromXml(): void
    {
        $flowActions = FlowAction::createFromXmlFile(__DIR__ . '/../_fixtures/valid/flowActionWithFlowActions.xml');
        static::assertCount(1, $flowActions->getActions()->getActions());
        $config = $flowActions->getActions()->getActions()[0]->getConfig()->getConfig();
        static::assertCount(5, $config);
        /**
         * @var Component $firstComponent
         */
        $firstComponent = $config[4];

        static::assertEquals('sw-entity-single-select', $firstComponent->getComponentName());
        static::assertEquals('exampleProduct', $firstComponent->getName());
        static::assertEquals('product', $firstComponent->getEntity());
        static::assertEquals([
            'en-GB' => 'Component label',
            'de-DE' => 'Component label DE', ], $firstComponent->getLabel());
    }
}
