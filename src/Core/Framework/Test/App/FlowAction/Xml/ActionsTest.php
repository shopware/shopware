<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\FlowAction\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\FlowAction\FlowAction;
use Shopware\Core\Framework\Feature;

class ActionsTest extends TestCase
{
    protected function setUp(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_17540', $this);
    }

    public function testFromXml(): void
    {
        $flowActions = FlowAction::createFromXmlFile(__DIR__ . '/../_fixtures/valid/flowActionWithFlowActions.xml');
        static::assertCount(1, $flowActions->getActions()->getActions());
    }
}
