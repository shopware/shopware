<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\FlowAction;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\FlowAction\AppFlowActionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class FlowActionTranslationTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testHeadlineAndDescriptionTranslation(): void
    {
        /** @var EntityRepository $appFlowActionRepository */
        $appFlowActionRepository = $this->getContainer()->get('app_flow_action.repository');

        $idFlowAction = $this->registerFlowAction();

        /** @var AppFlowActionEntity $appFlowAction */
        $appFlowAction = $appFlowActionRepository->search(new Criteria([$idFlowAction]), Context::createDefaultContext())->get($idFlowAction);

        static::assertEquals('Description for action', $appFlowAction->getDescription());
        static::assertEquals('Headline for action', $appFlowAction->getHeadline());
        static::assertEquals('Label for action', $appFlowAction->getLabel());
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
                        'headline' => ['en-GB' => 'Headline for action'],
                        'description' => ['en-GB' => 'Description for action'],
                        'label' => ['en-GB' => 'Label for action'],
                        'url' => 'http://xxxxx',
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        return $idFlowAction;
    }
}
