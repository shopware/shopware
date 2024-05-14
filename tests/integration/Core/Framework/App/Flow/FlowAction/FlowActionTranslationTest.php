<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Flow\FlowAction;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\FlowAction\AppFlowActionCollection;
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
        /** @var EntityRepository<AppFlowActionCollection> $appFlowActionRepository */
        $appFlowActionRepository = $this->getContainer()->get('app_flow_action.repository');

        $idFlowAction = $this->registerFlowAction();

        $appFlowAction = $appFlowActionRepository->search(new Criteria([$idFlowAction]), Context::createDefaultContext())->getEntities()->get($idFlowAction);
        static::assertNotNull($appFlowAction);

        static::assertSame('Description for action', $appFlowAction->getDescription());
        static::assertSame('Headline for action', $appFlowAction->getHeadline());
        static::assertSame('Label for action', $appFlowAction->getLabel());
    }

    private function registerFlowAction(): string
    {
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
