<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Newsletter\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\PlatformRequest;

class NewsletterSubscribeRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

    /**
     * @var TestDataCollection
     */
    private $ids;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
    }

    public function testSubscribeWithoutFields(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/newsletter/subscribe',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
        static::assertCount(3, $response['errors']);

        $errors = array_column(array_column($response['errors'], 'source'), 'pointer');

        static::assertContains('/email', $errors);
        static::assertContains('/option', $errors);
        static::assertContains('/storefrontUrl', $errors);
    }

    public function testSubscribeWithInvalidStorefrontUrl(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/newsletter/subscribe',
                [
                    'email' => 'test@test.de',
                    'option' => 'direct',
                    'storefrontUrl' => 'https://google.de',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
        static::assertCount(1, $response['errors']);

        $errors = array_column(array_column($response['errors'], 'source'), 'pointer');
        static::assertContains('/storefrontUrl', $errors);
    }

    public function testSubscribe(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/newsletter/subscribe',
                [
                    'email' => 'test@test.de',
                    'option' => 'direct',
                    'storefrontUrl' => 'http://localhost',
                ]
            );

        $count = (int) $this->getContainer()->get(Connection::class)->fetchColumn('SELECT COUNT(*) FROM newsletter_recipient WHERE email = "test@test.de" AND status = "direct"');
        static::assertSame(1, $count);
    }
}
