<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\RevocationRequest\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\MailTemplateTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('after-sales')]
#[Group('store-api')]
class RevocationRequestRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MailTemplateTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->eventDispatcher = static::getContainer()->get('event_dispatcher');
    }

    public function testRequest(): void
    {
        $listenerIsCalled = false;
        $revocationRequestCallback = static function (MailSentEvent $event) use (&$listenerIsCalled): void {
            $listenerIsCalled = true;
            static::assertSame('Revocation request sent', $event->getSubject());
        };

        $this->addEventListener($this->eventDispatcher, MailSentEvent::class, $revocationRequestCallback);

        $this->browser
            ->request(
                Request::METHOD_POST,
                '/store-api/revocation-request-form',
                [
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'email' => 'test@example.com',
                    'contractNumber' => 'SW123456789',
                    'comment' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('individualSuccessMessage', $response);
        static::assertEmpty($response['individualSuccessMessage']);
        static::assertTrue($listenerIsCalled);

        $this->eventDispatcher->removeListener(MailSentEvent::class, $revocationRequestCallback);
    }

    public function testRequestWithInvalidData(): void
    {
        $listenerIsCalled = false;
        $revocationRequestCallback = static function (MailSentEvent $event) use (&$listenerIsCalled): void {
            $listenerIsCalled = true;
            static::assertSame('Revocation request sent', $event->getSubject());
        };

        $this->addEventListener($this->eventDispatcher, MailSentEvent::class, $revocationRequestCallback);

        $this->browser
            ->request(
                Request::METHOD_POST,
                '/store-api/revocation-request-form',
                [
                    'firstName' => '',
                    'lastName' => '',
                    'email' => '',
                    'contractNumber' => '',
                    'comment' => '',
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);

        static::assertCount(4, $response['errors']);
        static::assertFalse($listenerIsCalled);

        $this->eventDispatcher->removeListener(MailSentEvent::class, $revocationRequestCallback);
    }
}
