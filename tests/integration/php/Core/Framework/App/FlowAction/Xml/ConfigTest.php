<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\FlowAction\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\FlowAction\FlowAction;

/**
 * @internal
 */
class ConfigTest extends TestCase
{
    public function testFromXml(): void
    {
        $flowActions = FlowAction::createFromXmlFile(__DIR__ . '/../_fixtures/valid/flowActionWithFlowActions.xml');
        static::assertNotNull($flowActions->getActions());

        static::assertCount(1, $flowActions->getActions()->getActions());
        static::assertCount(4, $flowActions->getActions()->getActions()[0]->getConfig()->getConfig());
    }
}
