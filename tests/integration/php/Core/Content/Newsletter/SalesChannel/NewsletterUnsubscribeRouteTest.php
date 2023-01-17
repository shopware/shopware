<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Newsletter\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Newsletter\Event\NewsletterUnsubscribeEvent;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 * @group store-api
 * @covers \Shopware\Core\Content\Newsletter\SalesChannel\NewsletterUnsubscribeRoute
 */
class NewsletterUnsubscribeRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $systemConfig = $this->getContainer()->get(SystemConfigService::class);
        static::assertNotNull($systemConfig);
        $systemConfig->set('core.newsletter.doubleOptIn', false);
    }

    public function testUnsubscribe(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/newsletter/subscribe',
                [
                    'email' => 'test@test.de',
                    'option' => 'direct',
                    'storefrontUrl' => 'http://localhost',
                ]
            );

        $count = (int) $this->getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM newsletter_recipient WHERE email = "test@test.de" AND status = "direct"');
        static::assertSame(1, $count);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');

        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $this->addEventListener($dispatcher, NewsletterUnsubscribeEvent::class, $listener);

        $this->browser
            ->request(
                'POST',
                '/store-api/newsletter/unsubscribe',
                [
                    'email' => 'test@test.de',
                ]
            );

        $count = (int) $this->getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM newsletter_recipient WHERE email = "test@test.de" AND status = "direct"');
        static::assertSame(0, $count);
    }
}
