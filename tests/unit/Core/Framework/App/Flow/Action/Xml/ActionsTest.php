<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Flow\Action\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Flow\Action\Xml\Action;
use Shopware\Core\Framework\App\Flow\Action\Xml\Actions;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\App\Flow\Action\Xml\Actions
 */
class ActionsTest extends TestCase
{
    public function testFromXml(): void
    {
        $document = XmlUtils::loadFile(
            __DIR__ . '/../../../_fixtures/Resources/flow-action.xml',
            __DIR__ . '/../../../../../../../../src/Core/Framework/App/FlowAction/Schema/flow-action-1.0.xsd'
        );

        $actions = $document->getElementsByTagName('flow-actions')->item(0);
        static::assertNotNull($actions);

        $action = Actions::fromXml($actions);
        static::assertCount(1, $action->getActions());
        static::assertInstanceOf(Action::class, $action->getActions()[0]);
    }
}
