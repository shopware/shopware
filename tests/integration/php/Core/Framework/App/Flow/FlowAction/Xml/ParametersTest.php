<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Flow\FlowAction\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Flow\Action\Action;
use Shopware\Core\Framework\Feature;

/**
 * @internal
 */
class ParametersTest extends TestCase
{
    public function testFromXml(): void
    {
        $flowActionsFile = Feature::isActive('v6.6.0.0') ? '/../_fixtures/valid/major/flow.xml' : '/../_fixtures/valid/minor/flow-action.xml';
        $flowActions = Action::createFromXmlFile(__DIR__ . $flowActionsFile);

        static::assertNotNull($flowActions->getActions());
        static::assertCount(1, $flowActions->getActions()->getActions());

        $firstAction = $flowActions->getActions()->getActions()[0];
        $parameters = $firstAction->getParameters();

        static::assertCount(3, $parameters->getParameters());
    }
}
