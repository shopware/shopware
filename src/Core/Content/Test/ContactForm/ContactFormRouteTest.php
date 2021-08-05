<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ContactForm;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\MailTemplateTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @group store-api
 */
class ContactFormRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MailTemplateTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->assignMailtemplatesToSalesChannel(Defaults::SALES_CHANNEL, $this->ids->context);
    }

    public function testContactFormSendMail(): void
    {
        /** @var EventDispatcher $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $phpunit = $this;
        $eventDidRun = false;
        $listenerClosure = function (MailSentEvent $event) use (&$eventDidRun, $phpunit): void {
            $eventDidRun = true;
            $phpunit->assertStringContainsString('Contact email address: test@shopware.com', $event->getContents()['text/html']);
            $phpunit->assertStringContainsString('essage: Lorem ipsum dolor sit amet', $event->getContents()['text/html']);
        };

        $dispatcher->addListener(MailSentEvent::class, $listenerClosure);

        $this->browser
            ->request(
                'POST',
                '/store-api/contact-form',
                [
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Firstname',
                    'lastName' => 'Lastname',
                    'email' => 'test@shopware.com',
                    'phone' => '12345/6789',
                    'subject' => 'Subject',
                    'comment' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('individualSuccessMessage', $response);
        static::assertEmpty($response['individualSuccessMessage']);

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);

        static::assertTrue($eventDidRun, 'The mail.sent Event did not run');
    }

    /**
     * @dataProvider navigationProvider
     */
    public function testContactFormSendMailWithNavigationIdAndSlotId(string $entityName): void
    {
        switch ($entityName) {
            case LandingPageDefinition::ENTITY_NAME:
                list($navigationId, $slotId) = $this->createLandingPageData();

                break;
            case ProductDefinition::ENTITY_NAME:
                list($navigationId, $slotId) = $this->createProductData();

                break;
            default:
                list($navigationId, $slotId) = $this->createCategoryData(true);
        }

        /** @var EventDispatcher $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $phpunit = $this;
        $eventDidRun = false;
        $recipients = [];
        $listenerClosure = function (MailSentEvent $event) use (&$eventDidRun, &$recipients, $phpunit): void {
            $eventDidRun = true;
            $recipients = $event->getRecipients();
            $phpunit->assertStringContainsString('Contact email address: test@shopware.com', $event->getContents()['text/html']);
            $phpunit->assertStringContainsString('essage: Lorem ipsum dolor sit amet', $event->getContents()['text/html']);
        };

        $dispatcher->addListener(MailSentEvent::class, $listenerClosure);

        $this->browser
            ->request(
                'POST',
                '/store-api/contact-form',
                [
                    'salutationId' => $this->getValidSalutationId(),
                    'navigationId' => $navigationId,
                    'slotId' => $slotId,
                    'entityName' => $entityName,
                    'firstName' => 'Firstname',
                    'lastName' => 'Lastname',
                    'email' => 'test@shopware.com',
                    'phone' => '12345/6789',
                    'subject' => 'Subject',
                    'comment' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertArrayHasKey('individualSuccessMessage', $response);
        static::assertEmpty($response['individualSuccessMessage']);

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);

        static::assertTrue($eventDidRun, 'The mail.sent Event did not run');
        static::assertArrayHasKey('h.mac@example.com', $recipients);
    }

    public function navigationProvider(): \Generator
    {
        yield 'Category with Slot Config' => [CategoryDefinition::ENTITY_NAME];
        yield 'Landing Page with Slot Config' => [LandingPageDefinition::ENTITY_NAME];
        yield 'Product Page with Slot Config' => [ProductDefinition::ENTITY_NAME];
    }

    public function testContactFormSendMailWithSlotId(): void
    {
        list($categoryId) = $this->createCategoryData();

        $formSlotId = $this->ids->create('form-slot');
        $this->createCmsFormData($formSlotId);

        /** @var EventDispatcher $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $phpunit = $this;
        $eventDidRun = false;
        $listenerClosure = function (MailSentEvent $event) use (&$eventDidRun, $phpunit): void {
            $eventDidRun = true;
            $phpunit->assertStringContainsString('Contact email address: test@shopware.com', $event->getContents()['text/html']);
            $phpunit->assertStringContainsString('essage: Lorem ipsum dolor sit amet', $event->getContents()['text/html']);
        };

        $dispatcher->addListener(MailSentEvent::class, $listenerClosure);

        $this->browser
            ->request(
                'POST',
                '/store-api/contact-form',
                [
                    'salutationId' => $this->getValidSalutationId(),
                    'navigationId' => $categoryId,
                    'slotId' => $formSlotId,
                    'firstName' => 'Firstname',
                    'lastName' => 'Lastname',
                    'email' => 'test@shopware.com',
                    'phone' => '12345/6789',
                    'subject' => 'Subject',
                    'comment' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('individualSuccessMessage', $response);
        static::assertEmpty($response['individualSuccessMessage']);

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);

        static::assertTrue($eventDidRun, 'The mail.sent Event did not run');
    }

    private function createCategoryData(bool $withSlotConfig = false): array
    {
        $contactCategoryId = $this->ids->get('contact-category-test');

        $slotId = $this->ids->create('form-slot');
        $slotConfig = $withSlotConfig ? [
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
        ] : [];

        $data = [
            [
                'id' => $contactCategoryId,
                'translations' => [
                    [
                        'name' => 'EN-Entry',
                        'languageId' => Defaults::LANGUAGE_SYSTEM,
                        'slotConfig' => $slotConfig,
                    ],
                ],
            ],
        ];
        $this->getContainer()->get('category.repository')->create($data, $this->ids->context);

        return [$contactCategoryId, $slotId];
    }

    private function createCmsFormData(string $slotId): void
    {
        $cmsData = [
            [
                'id' => $this->ids->create('cms-page'),
                'name' => 'test page',
                'type' => 'landingpage',
                'sections' => [
                    [
                        'id' => $this->ids->create('section'),
                        'type' => 'default',
                        'position' => 0,
                        'blocks' => [
                            [
                                'type' => 'form',
                                'position' => 0,
                                'slots' => [
                                    [
                                        'id' => $slotId,
                                        'type' => 'form',
                                        'slot' => 'content',
                                        'config' => [
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
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->getContainer()->get('cms_page.repository')->create($cmsData, $this->ids->context);
    }

    private function createLandingPageData(): array
    {
        $landingPageId = $this->ids->get('contact-landingpage-test');

        $slotId = $this->ids->create('form-slot');
        $slotConfig = [
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
        ];

        $data = [
            [
                'id' => $landingPageId,
                'name' => Uuid::randomHex(),
                'url' => Uuid::randomHex(),
                'salesChannels' => [
                    ['id' => Defaults::SALES_CHANNEL],
                ],
                'slotConfig' => $slotConfig,
            ],
        ];
        $this->getContainer()->get('landing_page.repository')->create($data, $this->ids->context);

        return [$landingPageId, $slotId];
    }

    private function createProductData(): array
    {
        $productId = $this->ids->get('contact-product-test');

        $slotId = $this->ids->create('form-slot');
        $slotConfig = [
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
        ];

        $data = [
            [
                'id' => $productId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'Test Product',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10.99, 'net' => 11.99, 'linked' => false]],
                'manufacturer' => ['name' => 'create'],
                'taxId' => $this->getValidTaxId(),
                'active' => true,
                'slotConfig' => $slotConfig,
            ],
        ];
        $this->getContainer()->get('product.repository')->create($data, $this->ids->context);

        return [$productId, $slotId];
    }
}
