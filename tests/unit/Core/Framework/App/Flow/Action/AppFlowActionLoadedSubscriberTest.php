<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Flow\Action;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\FlowAction\AppFlowActionDefinition;
use Shopware\Core\Framework\App\Aggregate\FlowAction\AppFlowActionEntity;
use Shopware\Core\Framework\App\Flow\Action\AppFlowActionLoadedSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(AppFlowActionLoadedSubscriber::class)]
class AppFlowActionLoadedSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertEquals([
            'app_flow_action.loaded' => 'unserialize',
        ], AppFlowActionLoadedSubscriber::getSubscribedEvents());
    }

    public function testUnserialize(): void
    {
        $idFlowAction = Uuid::randomHex();

        $appFlowAction = new AppFlowActionEntity();
        $appFlowAction->setId($idFlowAction);
        $iconPath = __DIR__ . '/../../Manifest/_fixtures/icon.png';

        $fileIcon = '';
        if (file_exists($iconPath)) {
            $fileIcon = \file_get_contents($iconPath);
        }

        $appFlowAction->setIconRaw($fileIcon !== false ? $fileIcon : null);

        $subscriber = new AppFlowActionLoadedSubscriber();
        $event = new EntityLoadedEvent(new AppFlowActionDefinition(), [$appFlowAction], Context::createDefaultContext());

        $subscriber->unserialize($event);
        static::assertNotFalse($fileIcon);

        static::assertEquals(
            base64_encode($fileIcon),
            $appFlowAction->getIcon()
        );
    }
}
