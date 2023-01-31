<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\FlowAction;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\FlowAction\AppFlowActionEntity;
use Shopware\Core\Framework\App\FlowAction\AppFlowActionLoadedSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class AppFlowActionLoadedSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testGetSubscribedEvents(): void
    {
        static::assertEquals([
            'app_flow_action.loaded' => 'unserialize',
        ], AppFlowActionLoadedSubscriber::getSubscribedEvents());
    }

    public function testUnserialize(): void
    {
        /** @var EntityRepository $appFlowActionRepository */
        $appFlowActionRepository = $this->getContainer()->get('app_flow_action.repository');

        $idFlowAction = $this->registerFlowAction();

        /** @var AppFlowActionEntity $appFlowAction */
        $appFlowAction = $appFlowActionRepository->search(new Criteria([$idFlowAction]), Context::createDefaultContext())->get($idFlowAction);
        static::assertNotNull($appFlowAction);

        $icon = \file_get_contents(__DIR__ . '/../Manifest/_fixtures/test/icon.png');
        static::assertNotFalse($icon);

        static::assertEquals(
            base64_encode($icon),
            $appFlowAction->getIcon()
        );
    }

    private function registerFlowAction(): string
    {
        /** @var EntityRepository $appRepository */
        $appRepository = $this->getContainer()->get('app.repository');

        $idFlowAction = Uuid::randomHex();

        $appRepository->create([
            [
                'id' => Uuid::randomHex(),
                'name' => 'App',
                'path' => __DIR__ . '/../Manifest/_fixtures/test',
                'version' => '0.0.1',
                'label' => 'test App',
                'accessToken' => 'test',
                'integration' => [
                    'label' => 'App1',
                    'writeAccess' => false,
                    'accessKey' => 'test',
                    'secretAccessKey' => 'test',
                ],
                'aclRole' => [
                    'name' => 'App1',
                ],
                'flowActions' => [
                    [
                        'id' => $idFlowAction,
                        'name' => 'FlowActiontest',
                        'sign' => 'Test',
                        'label' => 'Flow Action test',
                        'iconRaw' => file_get_contents(__DIR__ . '/../Manifest/_fixtures/test/icon.png'),
                        'url' => 'http://xxxxx',
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        return $idFlowAction;
    }
}
