<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Content\Flow\Dispatching\FlowFactory;
use Shopware\Core\Content\Flow\Dispatching\Message\FlowActionHandler;
use Shopware\Core\Content\Flow\Dispatching\Message\FlowActionMessage;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Struct\ActionSequence;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(FlowActionHandler::class)]
class FlowActionHandlerTest extends TestCase
{
    #[DoesNotPerformAssertions]
    public function testInvokeDoesNothingIfActionNotFound(): void
    {
        $factory = $this->createMock(FlowFactory::class);
        $handler = new FlowActionHandler($factory, []);

        $actionSequence = new ActionSequence();
        $actionSequence->action = SendMailAction::ACTION_NAME;

        $flow = new StorableFlow('some-flow', Context::createCLIContext());

        $message = new FlowActionMessage($actionSequence, $flow, Context::createCLIContext());

        $handler($message);
    }

    public function testInvokeCallsHandleFlow(): void
    {
        $factory = $this->createMock(FlowFactory::class);
        $sendMailAction = $this->createMock(SendMailAction::class);

        $handler = new FlowActionHandler($factory, [SendMailAction::ACTION_NAME => $sendMailAction]);

        $actionSequence = new ActionSequence();
        $actionSequence->action = SendMailAction::ACTION_NAME;

        $flow = new StorableFlow('some-flow', Context::createCLIContext());

        $message = new FlowActionMessage($actionSequence, $flow, Context::createCLIContext());

        $factory->expects($this->once())
            ->method('restore')
            ->willReturn($flow);

        $handler($message);
    }
}
