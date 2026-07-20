<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Newsletter\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('after-sales')]
#[Group('store-api')]
class NewsletterConfirmRouteTest extends TestCase
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
    }

    public function testEmptyRequest(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/newsletter/confirm',
                [
                ]
            );

        $response = $this->browser->getResponse();
        $responseBody = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        static::assertSame('CONTENT__NEWSLETTER_RECIPIENT_NOT_FOUND', $responseBody['errors'][0]['code']);
    }

    public function testWithInvalidHash(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/newsletter/confirm',
                [
                    'email' => 'test@test.de',
                    'hash' => 'foooo',
                ]
            );

        $response = $this->browser->getResponse();
        $responseBody = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        static::assertSame('CONTENT__NEWSLETTER_RECIPIENT_NOT_FOUND', $responseBody['errors'][0]['code']);
    }

    public function testWithInvalidMail(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/newsletter/confirm',
                [
                    'email' => 'xxxxx@test.de',
                    'hash' => 'foooo',
                ]
            );

        $response = $this->browser->getResponse();
        $responseBody = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        static::assertSame('CONTENT__NEWSLETTER_RECIPIENT_NOT_FOUND', $responseBody['errors'][0]['code']);
    }

    public function testConfirm(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/newsletter/subscribe',
                [
                    'email' => 'test@test.de',
                    'option' => 'subscribe',
                    'storefrontUrl' => 'http://localhost',
                ]
            );

        $count = (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM newsletter_recipient WHERE email = "test@test.de"');
        static::assertSame(1, $count);
        $hash = static::getContainer()->get(Connection::class)->fetchOne('SELECT hash FROM newsletter_recipient WHERE email = "test@test.de"');

        $this->browser
            ->request(
                'POST',
                '/store-api/newsletter/confirm',
                [
                    'email' => 'test@test.de',
                    'hash' => $hash,
                ]
            );

        $response = $this->browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $status = static::getContainer()->get(Connection::class)->fetchOne('SELECT status FROM newsletter_recipient WHERE email = "test@test.de"');
        static::assertSame('optIn', $status);
    }
}
