<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Newsletter\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\PlatformRequest;

class NewsletterConfirmRouteTest extends TestCase
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

    public function testEmptyRequest(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/newsletter/confirm',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('CONTENT__NEWSLETTER_RECIPIENT_NOT_FOUND', $response['errors'][0]['code']);
    }

    public function testWithInvalidHash(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/newsletter/confirm',
                [
                    'email' => 'test@test.de',
                    'hash' => 'foooo',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('CONTENT__NEWSLETTER_RECIPIENT_NOT_FOUND', $response['errors'][0]['code']);
    }

    public function testWithInvalidMail(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/newsletter/confirm',
                [
                    'email' => 'xxxxx@test.de',
                    'hash' => 'foooo',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('CONTENT__NEWSLETTER_RECIPIENT_NOT_FOUND', $response['errors'][0]['code']);
    }

    public function testConfirm(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/newsletter/subscribe',
                [
                    'email' => 'test@test.de',
                    'option' => 'subscribe',
                    'storefrontUrl' => 'http://localhost',
                ]
            );

        $count = (int) $this->getContainer()->get(Connection::class)->fetchColumn('SELECT COUNT(*) FROM newsletter_recipient WHERE email = "test@test.de"');
        static::assertSame(1, $count);
        $hash = $this->getContainer()->get(Connection::class)->fetchColumn('SELECT hash FROM newsletter_recipient WHERE email = "test@test.de"');

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/newsletter/confirm',
                [
                    'email' => 'test@test.de',
                    'hash' => $hash,
                ]
            );

        $status = $this->getContainer()->get(Connection::class)->fetchColumn('SELECT status FROM newsletter_recipient WHERE email = "test@test.de"');
        static::assertSame('optIn', $status);
    }
}
