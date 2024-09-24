<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\MessageHandler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Service\Message\UpdateServiceMessage;
use Shopware\Core\Service\MessageHandler\UpdateServiceHandler;
use Shopware\Core\Service\ServiceLifecycle;

/**
 * @internal
 */
#[CoversClass(UpdateServiceHandler::class)]
class UpdateServiceHandlerTest extends TestCase
{
    public function testHandlerDelegatesToServiceLifecycle(): void
    {
        $lifecycle = $this->createMock(ServiceLifecycle::class);
        $lifecycle->expects(static::once())->method('update')->with('MyCoolService');

        $handler = new UpdateServiceHandler($lifecycle);
        $handler->__invoke(new UpdateServiceMessage('MyCoolService'));
    }
}
