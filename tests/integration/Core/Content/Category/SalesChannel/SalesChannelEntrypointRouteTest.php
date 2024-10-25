<?php
declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Category\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Event\SalesChannelEntrypointEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Group('store-api')]
class SalesChannelEntrypointRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    private EventDispatcherInterface $listener;

    protected function setUp(): void
    {
        $this->listener = $this->getContainer()->get(EventDispatcherInterface::class);

        $this->ids = new TestDataCollection();

        $this->createData();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'entrypointIds' => ['additional' => $this->ids->get('additionalEntrypointId')],
        ]);
    }

    public function testCmsPageResolved(): void
    {
        $this->listener->addListener(SalesChannelEntrypointEvent::class, function (SalesChannelEntrypointEvent $event): void {
            $event->addEntrypointType('additional');
        });

        $this->browser->request(
            'GET',
            '/store-api/entry-point'
        );
        $content = $this->browser->getResponse()->getContent();

        static::assertStringContainsString($this->ids->get('additionalEntrypointId'), (string) $content);
    }

    private function createData(): void
    {
        $data = [
            'id' => $this->ids->create('additionalEntrypointId'),
            'name' => 'Custom entry point',
            'type' => 'folder',
            'active' => true,
        ];

        $this->getContainer()->get('category.repository')
            ->create([$data], Context::createDefaultContext());
    }
}
