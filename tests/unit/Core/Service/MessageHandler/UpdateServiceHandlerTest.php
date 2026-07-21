<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\MessageHandler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\Message\UpdateServiceMessage;
use Shopware\Core\Service\MessageHandler\UpdateServiceHandler;
use Shopware\Core\Service\ServiceLifecycle;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UpdateServiceHandler::class)]
class UpdateServiceHandlerTest extends TestCase
{
    public function testHandlerDelegatesToServiceLifecycle(): void
    {
        $serviceLifecycle = $this->createMock(ServiceLifecycle::class);
        $serviceLifecycle->expects($this->once())->method('update')->with('MyCoolService');

        $handler = new UpdateServiceHandler($serviceLifecycle);
        $handler->__invoke(new UpdateServiceMessage('MyCoolService'));
    }
}
