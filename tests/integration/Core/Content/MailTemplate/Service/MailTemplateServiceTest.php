<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\MailTemplate\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopware\Core\Content\Mail\Service\MailService;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Content\MailTemplate\Service\Event\MailErrorEvent;
use Shopware\Core\Content\MailTemplate\Service\MailDataProvider;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateService;
use Shopware\Core\Content\MailTemplate\Validation\MailTemplateRenderError;
use Shopware\Core\Content\MailTemplate\Validation\MailTemplateRenderSuccess;
use Shopware\Core\Content\Product\SalesChannel\Review\Event\ReviewFormEvent;
use Shopware\Core\Content\Test\Flow\OrderActionTrait;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mime\Email;

/**
 * @internal
 */
#[Package('after-sales')]
class MailTemplateServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use OrderActionTrait;

    private MailTemplateService $mailTemplateService;

    private Context $context;

    /**
     * @var EntityRepository<MailTemplateCollection>
     */
    private EntityRepository $mailTemplateRepository;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->mailTemplateRepository = static::getContainer()->get('mail_template.repository');
        $this->mailTemplateService = static::getContainer()->get(MailTemplateService::class);
        $this->context = Context::createDefaultContext();
        $this->ids = new IdsCollection();
    }

    public function testLoadTemplateNoTemplateFound(): void
    {
        $this->expectExceptionObject(MailTemplateException::templateNotFound());

        $this->mailTemplateService->loadTemplate(Uuid::randomHex(), $this->context);
    }

    public function testLoadTemplate(): void
    {
        $id = Uuid::randomHex();
        $this->mailTemplateRepository->create([
            [
                'id' => $id,
                'systemDefault' => false,
                'mailTemplateType' => [
                    'name' => 'Test',
                    'technicalName' => 'test',
                    'availableEntities' => [],
                ],
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'subject' => 'Test',
                        'contentHtml' => 'Some html text',
                        'contentPlain' => 'Some plain text',
                        'senderName' => 'Shopware',
                    ],
                ],
            ],
        ], $this->context);

        $mailTemplate = $this->mailTemplateService->loadTemplate($id, $this->context);

        static::assertSame('Test', $mailTemplate->getSubject());
        static::assertSame('Some html text', $mailTemplate->getContentHtml());
        static::assertSame('Some plain text', $mailTemplate->getContentPlain());
        static::assertSame('Shopware', $mailTemplate->getSenderName());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testPreviewNonExistingEntitiesErrorInStrictMode(): void
    {
        $templateContent = 'Order ID: {{ order.id }}';
        $rendered = $this->mailTemplateService->preview(
            ['contentHtml' => $templateContent, 'contentPlain' => $templateContent],
            $this->context,
            true,
            ReviewFormEvent::class
        );

        static::assertCount(2, $rendered);

        static::assertSame(MailTemplateRenderError::TYPE, $rendered->get('contentHtml')?->getType());
        static::assertMatchesRegularExpression('/^Failed rendering string template using Twig: Variable "order" does not exist in "[0-9a-f]{32}" at line 1.$/', $rendered->get('contentHtml')->getContent());
        static::assertSame(MailTemplateRenderError::TYPE, $rendered->get('contentPlain')?->getType());
        static::assertMatchesRegularExpression('/^Failed rendering string template using Twig: Variable "order" does not exist in "[0-9a-f]{32}" at line 1.$/', $rendered->get('contentPlain')->getContent());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testPreviewIgnoresMissingVariablesInNonStrictMode(): void
    {
        $rendered = $this->mailTemplateService->preview(
            ['contentHtml' => 'Order ID: {{ order.id }}', 'contentPlain' => 'Order ID: {{ order.id }}'],
            $this->context,
            false,
            ReviewFormEvent::class
        );

        static::assertCount(2, $rendered);

        $expected = new MailTemplateRenderSuccess('Order ID: ');

        static::assertEquals($expected, $rendered->get('contentHtml'));
        static::assertEquals($expected, $rendered->get('contentPlain'));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testPreviewCanRenderVariables(): void
    {
        $rendered = $this->mailTemplateService->preview(
            ['contentHtml' => 'Order ID: {{ order.id }}', 'contentPlain' => 'Order ID: {{ order.id }}'],
            $this->context,
            false,
            CheckoutOrderPlacedEvent::class
        );

        static::assertCount(2, $rendered);

        $contentHtml = $rendered->get('contentHtml');
        static::assertNotNull($contentHtml);
        static::assertSame(MailTemplateRenderSuccess::TYPE, $contentHtml->getType());

        $renderedHtml = $contentHtml->getContent();
        static::assertStringContainsString('Order ID: ', $renderedHtml);
        static::assertTrue(Uuid::isValid(\explode('Order ID: ', $renderedHtml)[1]));

        $contentPlain = $rendered->get('contentPlain');
        static::assertNotNull($contentPlain);
        static::assertSame(MailTemplateRenderSuccess::TYPE, $contentPlain->getType());

        $renderedPlain = $contentPlain->getContent();
        static::assertStringContainsString('Order ID: ', $renderedPlain);
        static::assertTrue(Uuid::isValid(\explode('Order ID: ', $renderedPlain)[1]));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testSendNoEntitiesButNotRequired(): void
    {
        $data = [
            'contentHtml' => 'test',
            'contentPlain' => 'test',
            'subject' => 'Test',
            'senderName' => 'Shopware',
        ];

        $mailService = $this->createMock(MailService::class);
        $mailService
            ->expects($this->once())
            ->method('send')
            ->with(
                static::callback(function (array $data) {
                    // We check if the data gets correctly enriched with the template data
                    $expectedData = [
                        'contentHtml' => 'test',
                        'contentPlain' => 'test',
                        'subject' => 'Test',
                        'senderName' => 'Shopware',
                    ];

                    foreach ($expectedData as $key => $value) {
                        if (!\array_key_exists($key, $data)) {
                            return false;
                        }

                        if ($data[$key] !== $value) {
                            return false;
                        }
                    }

                    return true;
                }),
                $this->context,
                static::anything()
            );

        /** @var MailDataProvider $mailDataProvider */
        $mailDataProvider = static::getContainer()->get(MailDataProvider::class);

        /** @var StringTemplateRenderer $stringTemplateRenderer */
        $stringTemplateRenderer = static::getContainer()->get(StringTemplateRenderer::class);

        $mailTemplateService = new MailTemplateService(
            $mailService,
            $mailDataProvider,
            $this->mailTemplateRepository,
            $stringTemplateRenderer,
        );

        $mailTemplateService->getTemplateDataAndSend($data, $this->context, ContactFormEvent::class);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testSendNonExistingEntities(): void
    {
        $data = [
            'recipients' => [
                'test@shopware.com' => 'Test',
            ],
            'contentHtml' => 'Order ID: {{ order.id }}',
            'contentPlain' => 'Order ID: {{ order.id }}',
            'subject' => 'Test',
            'senderName' => 'Shopware',
        ];

        $state = new \stdClass();
        $state->throwable = null;

        $subscriber = new TestSubscriber($state);

        static::getContainer()->get('event_dispatcher')->addSubscriber($subscriber);

        $email = $this->mailTemplateService->getTemplateDataAndSend(
            $data,
            $this->context,
            ReviewFormEvent::class
        );

        static::assertNull($email);
        // @phpstan-ignore-next-line because throwable is set in the event listener but phpstan does not recognize this
        static::assertInstanceOf(AdapterException::class, $state->throwable);
        static::assertSame(
            AdapterException::STRING_TEMPLATE_RENDERING_FAILED,
            $state->throwable->getErrorCode()
        );
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testCanSend(): void
    {
        $data = [
            'recipients' => [
                'test@shopware.com' => 'Test',
            ],
            'contentHtml' => 'Order ID: {{ order.id }}',
            'contentPlain' => 'Order ID: {{ order.id }}',
            'subject' => 'Test',
            'senderName' => 'Shopware',
        ];

        $email = $this->mailTemplateService->getTemplateDataAndSend($data, $this->context, CheckoutOrderPlacedEvent::class);

        static::assertInstanceOf(Email::class, $email);

        $textBody = $email->getTextBody();
        static::assertIsString($textBody);
        static::assertMatchesRegularExpression('/"[\w \.]+"Order ID: [0-9a-f]{32}"[\w \.]+"/', $textBody);

        $htmlBody = $email->getHtmlBody();
        static::assertIsString($htmlBody);
        static::assertMatchesRegularExpression('/"[\w \.]+"Order ID: [0-9a-f]{32}"[\w \.]+"/', $htmlBody);

        static::assertSame('Test', $email->getSubject());
        static::assertSame('Shopware', $email->getFrom()[0]->getName());
        static::assertSame('Test', $email->getTo()[0]->getName());
        static::assertSame('test@shopware.com', $email->getTo()[0]->getAddress());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testCanSendMultipleEntities(): void
    {
        $data = [
            'recipients' => [
                'test@shopware.com' => 'Test',
            ],
            'contentHtml' => 'Main order ID: {{ order.id }}, Customer ID: {{ customer.id }}',
            'contentPlain' => 'Main order ID: {{ order.id }}, Customer ID: {{ customer.id }}',
            'subject' => 'Test',
            'senderName' => 'Shopware',
        ];

        $email = $this->mailTemplateService->getTemplateDataAndSend(
            $data,
            $this->context,
            CheckoutOrderPlacedEvent::class
        );

        static::assertInstanceOf(Email::class, $email);

        $textBody = $email->getTextBody();
        static::assertIsString($textBody);

        static::assertMatchesRegularExpression('/"[\w \.]+"Main order ID: [0-9a-f]{32}, Customer ID: [0-9a-f]{32}"[\w \.]+"/', $textBody);

        $htmlBody = $email->getHtmlBody();
        static::assertIsString($htmlBody);

        static::assertMatchesRegularExpression('/"[\w \.]+"Main order ID: [0-9a-f]{32}, Customer ID: [0-9a-f]{32}"[\w \.]+"/', $htmlBody);

        static::assertSame('Test', $email->getSubject());
        static::assertSame('Shopware', $email->getFrom()[0]->getName());
        static::assertSame('Test', $email->getTo()[0]->getName());
        static::assertSame('test@shopware.com', $email->getTo()[0]->getAddress());
    }
}

/**
 * @internal
 */
class TestSubscriber implements EventSubscriberInterface
{
    private \stdClass $state;

    public function __construct(\stdClass $state)
    {
        $this->state = $state;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MailErrorEvent::class => 'onMailError',
        ];
    }

    public function onMailError(MailErrorEvent $event): void
    {
        $this->state->throwable = $event->getThrowable();
    }
}
