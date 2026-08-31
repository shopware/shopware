<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\ContactForm;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\MailTemplateTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Package('discovery')]
#[Group('store-api')]
class ContactFormRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MailTemplateTestBehaviour;
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

    public function testContactFormSendMail(): void
    {
        $dispatcher = static::getContainer()->get('event_dispatcher');

        $eventDidRun = false;
        $listenerClosure = static function (MailSentEvent $event) use (&$eventDidRun): void {
            $eventDidRun = true;
            $htmlText = $event->getContents()['text/html'];
            self::assertIsString($htmlText);
            static::assertStringContainsString('Contact email address: test@shopware.com', $htmlText);
            static::assertStringContainsString('essage: Lorem ipsum dolor sit amet', $htmlText);
        };

        $this->addEventListener($dispatcher, MailSentEvent::class, $listenerClosure);

        $this->browser->request(
            'POST',
            '/store-api/contact-form',
            [
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Firstname',
                'lastName' => 'Lastname',
                'email' => 'test@shäpware.com',
                'phone' => '12345/6789',
                'subject' => 'Subject',
                'comment' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
            ]
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('individualSuccessMessage', $response);
        static::assertEmpty($response['individualSuccessMessage']);

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);

        static::assertTrue($eventDidRun, 'The mail.sent Event did not run');
    }

    public function testContactFormSendMailWithLandingPageContext(): void
    {
        [$navigationId, $slotId] = $this->createLandingPageData();

        $dispatcher = static::getContainer()->get('event_dispatcher');

        $eventDidRun = false;
        $recipients = [];
        $listenerClosure = static function (MailSentEvent $event) use (&$eventDidRun, &$recipients): void {
            $eventDidRun = true;
            $recipients = $event->getRecipients();
            $htmlText = $event->getContents()['text/html'];
            self::assertIsString($htmlText);
            static::assertStringContainsString('Contact email address: test@shopware.com', $htmlText);
            static::assertStringContainsString('essage: Lorem ipsum dolor sit amet', $htmlText);
        };

        $this->addEventListener($dispatcher, MailSentEvent::class, $listenerClosure);

        $this->browser->request(
            'POST',
            '/store-api/contact-form',
            [
                'salutationId' => $this->getValidSalutationId(),
                'navigationId' => $navigationId,
                'slotId' => $slotId,
                'entityName' => LandingPageDefinition::ENTITY_NAME,
                'firstName' => 'Firstname',
                'lastName' => 'Lastname',
                'email' => 'test@shopware.com',
                'phone' => '12345/6789',
                'subject' => 'Subject',
                'comment' => 'Lorem ipsum dolor sit amet',
            ]
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('individualSuccessMessage', $response);
        static::assertEmpty($response['individualSuccessMessage']);

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);

        static::assertTrue($eventDidRun, 'The mail.sent Event did not run');
        static::assertArrayHasKey('h.mac@example.com', $recipients);
    }

    #[DataProvider('contactFormWithDomainProvider')]
    public function testContactFormWithInvalid(string $firstName, string $lastName, \Closure $expectClosure): void
    {
        $this->browser->request(
            'POST',
            '/store-api/contact-form',
            [
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => 'test@shopware.com',
                'phone' => '12345/6789',
                'subject' => 'Subject',
                'comment' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
            ]
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $expectClosure($response);
    }

    public static function contactFormWithDomainProvider(): \Generator
    {
        yield 'subscribe with URL protocol HTTPS' => [
            'Y https://shopware.test',
            'Tran',
            static function (array $response): void {
                static::assertArrayHasKey('errors', $response);
                static::assertCount(1, $response['errors']);

                $errors = array_column(array_column($response['errors'], 'source'), 'pointer');

                static::assertContains('/firstName', $errors);
            },
        ];

        yield 'subscribe with URL protocol HTTP' => [
            'Y http://shopware.test',
            'Tran',
            static function (array $response): void {
                static::assertArrayHasKey('errors', $response);
                static::assertCount(1, $response['errors']);

                $errors = array_column(array_column($response['errors'], 'source'), 'pointer');

                static::assertContains('/firstName', $errors);
            },
        ];

        yield 'subscribe with URL localhost' => [
            'Y http://localhost:8080',
            'Tran',
            static function (array $response): void {
                static::assertArrayHasKey('errors', $response);
                static::assertCount(1, $response['errors']);

                $errors = array_column(array_column($response['errors'], 'source'), 'pointer');

                static::assertContains('/firstName', $errors);
            },
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function createLandingPageData(): array
    {
        $landingPageId = $this->ids->get('contact-landingpage-test');
        $slotId = $this->ids->create('form-slot');

        static::getContainer()->get('landing_page.repository')->create([
            [
                'id' => $landingPageId,
                'name' => Uuid::randomHex(),
                'url' => Uuid::randomHex(),
                'salesChannels' => [
                    ['id' => TestDefaults::SALES_CHANNEL],
                ],
                'slotConfig' => [
                    $slotId => [
                        'mailReceiver' => [
                            'source' => 'static',
                            'value' => ['h.mac@example.com'],
                        ],
                        'confirmationText' => [
                            'source' => 'static',
                            'value' => '',
                        ],
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        return [$landingPageId, $slotId];
    }
}
