<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\MailTemplate\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Service\MailService;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Content\MailTemplate\Service\Event\MailErrorEvent;
use Shopware\Core\Content\MailTemplate\Service\MailDataProvider;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateService;
use Shopware\Core\Content\Test\Flow\OrderActionTrait;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
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

    private Connection $connection;

    private Context $context;

    /**
     * @var EntityRepository<MailTemplateCollection>
     */
    private EntityRepository $mailTemplateRepository;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->mailTemplateRepository = static::getContainer()->get('mail_template.repository');
        $this->mailTemplateService = static::getContainer()->get(MailTemplateService::class);
        $this->context = Context::createDefaultContext();
        $this->ids = new IdsCollection();
    }

    public function testPreviewNoTemplateFound(): void
    {
        $errorThrown = false;

        try {
            $this->mailTemplateService->preview(Uuid::randomHex(), [], $this->context);
        } catch (\Throwable $e) {
            $errorThrown = true;

            static::assertInstanceOf(MailTemplateException::class, $e);
            static::assertSame(MailTemplateException::MAIL_TEMPLATE_NOT_FOUND, $e->getErrorCode());
            static::assertSame(MailTemplateException::templateNotFound()->getMessage(), $e->getMessage());
        }

        static::assertTrue($errorThrown, 'Expected MailTemplateException was not thrown.');
    }

    public function testPreviewInvalidContent(): void
    {
        $id = $this->createMailTemplate();

        $this->connection->update(
            'mail_template_translation',
            ['content_html' => null],
            ['mail_template_id' => Uuid::fromHexToBytes($id)]
        );

        $errorThrown = false;

        try {
            $this->mailTemplateService->preview($id, [], $this->context);
        } catch (\Throwable $e) {
            $errorThrown = true;

            static::assertInstanceOf(MailTemplateException::class, $e);
            static::assertSame(MailTemplateException::MAIL_INVALID_TEMPLATE_CONTENT, $e->getErrorCode());
            static::assertSame(MailTemplateException::invalidMailTemplateContent()->getMessage(), $e->getMessage());
        }

        static::assertTrue($errorThrown, 'Expected MailTemplateException was not thrown.');
    }

    public function testPreviewNoEntities(): void
    {
        $id = $this->createMailTemplate();

        $rendered = $this->mailTemplateService->preview(
            $id,
            [], // no entities provided
            $this->context
        );

        static::assertSame('test', $rendered);
    }

    public function testPreviewNonExistingEntitiesGetIgnored(): void
    {
        $id = $this->createMailTemplate(
            ['order' => 'order'],
            'Order ID: {{ order.id }}',
            'Order ID: {{ order.id }}',
        );

        $rendered = $this->mailTemplateService->preview(
            $id,
            [
                'order' => Uuid::randomHex(), // non-existing entity
            ],
            $this->context
        );

        static::assertSame('Order ID: ', $rendered);
    }

    public function testPreviewNonExistingEntitiesErrorInStrictMode(): void
    {
        $id = $this->createMailTemplate(
            ['order' => 'order'],
            'Order ID: {{ order.id }}', // order variable is required for rendering the template
            'Order ID: {{ order.id }}',
        );

        $errorThrown = false;

        try {
            $this->mailTemplateService->preview(
                $id,
                [
                    'order' => Uuid::randomHex(), // non-existing entity
                ],
                $this->context,
                true,
            );
        } catch (\Throwable $e) {
            $errorThrown = true;

            static::assertInstanceOf(AdapterException::class, $e);
            static::assertSame(AdapterException::STRING_TEMPLATE_RENDERING_FAILED, $e->getErrorCode());
        }

        static::assertTrue($errorThrown, 'Expected AdapterException was not thrown.');
    }

    public function testPreviewIgnoresMissingVariablesInNonStrictMode(): void
    {
        $id = $this->createMailTemplate(
            ['order' => 'order'],
            'Order ID: {{ order.id }}', // order variable is required for rendering the template
            'Order ID: {{ order.id }}',
        );

        $rendered = $this->mailTemplateService->preview(
            $id,
            [], // no entities provided
            $this->context
        );

        static::assertSame('Order ID: ', $rendered);
    }

    public function testPreviewCanRenderVariables(): void
    {
        $orderId = Uuid::randomHex();

        $customerId = $this->createCustomer();
        $this->createOrder($customerId, [
            'id' => $orderId,
        ]);

        $id = $this->createMailTemplate(
            ['order' => 'order'],
            'Order ID: {{ order.id }}',
            'Order ID: {{ order.id }}',
        );

        $rendered = $this->mailTemplateService->preview(
            $id,
            [
                'order' => $orderId,
            ],
            $this->context
        );

        static::assertSame('Order ID: ' . $orderId, $rendered);
    }

    public function testSendNoTemplateFound(): void
    {
        $errorThrown = false;

        try {
            $this->mailTemplateService->getTemplateDataAndSend([], Uuid::randomHex(), [], $this->context);
        } catch (\Throwable $e) {
            $errorThrown = true;

            static::assertInstanceOf(MailTemplateException::class, $e);
            static::assertSame(MailTemplateException::MAIL_TEMPLATE_NOT_FOUND, $e->getErrorCode());
            static::assertSame(MailTemplateException::templateNotFound()->getMessage(), $e->getMessage());
        }

        static::assertTrue($errorThrown, 'Expected MailTemplateException was not thrown.');
    }

    public function testSendNoEntitiesButNotRequired(): void
    {
        $id = $this->createMailTemplate();

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
                []
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

        $mailTemplateService->getTemplateDataAndSend([], $id, [], $this->context);
    }

    public function testSendNoEntitiesButRequired(): void
    {
        $id = $this->createMailTemplate(
            ['order' => 'order'],
            'Order ID: {{ order.id }}', // order variable is required for rendering the template
            'Order ID: {{ order.id }}',
        );

        $data = [
            'recipients' => [
                'test@shopware.com' => 'Test',
            ],
        ];

        $state = new \stdClass();
        $state->throwable = null;

        $subscriber = new TestSubscriber($state);

        static::getContainer()->get('event_dispatcher')->addSubscriber($subscriber);

        $email = $this->mailTemplateService->getTemplateDataAndSend(
            $data,
            $id,
            [], // no entities provided
            $this->context
        );

        static::assertNull($email);
        // @phpstan-ignore-next-line because throwable is set in the event listener but phpstan does not recognize this
        static::assertInstanceOf(AdapterException::class, $state->throwable);
        static::assertSame(
            AdapterException::STRING_TEMPLATE_RENDERING_FAILED,
            $state->throwable->getErrorCode()
        );
    }

    public function testSendNonExistingEntities(): void
    {
        $id = $this->createMailTemplate(
            ['order' => 'order'],
            'Order ID: {{ order.id }}', // order variable is required for rendering the template
            'Order ID: {{ order.id }}',
        );

        $data = [
            'recipients' => [
                'test@shopware.com' => 'Test',
            ],
        ];

        $state = new \stdClass();
        $state->throwable = null;

        $subscriber = new TestSubscriber($state);

        static::getContainer()->get('event_dispatcher')->addSubscriber($subscriber);

        $email = $this->mailTemplateService->getTemplateDataAndSend(
            $data,
            $id,
            ['order' => Uuid::randomHex()], // non-existing entity
            $this->context
        );

        static::assertNull($email);
        // @phpstan-ignore-next-line because throwable is set in the event listener but phpstan does not recognize this
        static::assertInstanceOf(AdapterException::class, $state->throwable);
        static::assertSame(
            AdapterException::STRING_TEMPLATE_RENDERING_FAILED,
            $state->throwable->getErrorCode()
        );
    }

    public function testOnlyEntitiesInAvailableEntitiesAreFetched(): void
    {
        // Create the order so the mail template service can fetch it if requested.
        // But 'order' will not be in availableEntities so rendering should fail due to missing variables.
        $orderId = Uuid::randomHex();

        $customerId = $this->createCustomer();
        $this->createOrder($customerId, [
            'id' => $orderId,
        ]);

        $id = $this->createMailTemplate(
            availableEntities: ['product' => 'product'],
            contentHtml: 'Order ID: {{ order.id }}',
            contentPlain: 'Order ID: {{ order.id }}',
        );

        $data = [
            'recipients' => [
                'test@shopware.com' => 'Test',
            ],
        ];

        $state = new \stdClass();
        $state->throwable = null;

        $subscriber = new TestSubscriber($state);

        static::getContainer()->get('event_dispatcher')->addSubscriber($subscriber);

        $email = $this->mailTemplateService->getTemplateDataAndSend($data, $id, ['order' => $orderId], $this->context);

        static::assertNull($email);
        // @phpstan-ignore-next-line because throwable is set in the event listener but phpstan does not recognize this
        static::assertInstanceOf(AdapterException::class, $state->throwable);
        static::assertSame(
            AdapterException::STRING_TEMPLATE_RENDERING_FAILED,
            $state->throwable->getErrorCode()
        );
    }

    public function testCanSend(): void
    {
        $orderId = Uuid::randomHex();

        $customerId = $this->createCustomer();
        $this->createOrder($customerId, [
            'id' => $orderId,
        ]);

        $id = $this->createMailTemplate(
            ['order' => 'order'],
            'Order ID: {{ order.id }}',
            'Order ID: {{ order.id }}',
        );

        $data = [
            'recipients' => [
                'test@shopware.com' => 'Test',
            ],
        ];

        $email = $this->mailTemplateService->getTemplateDataAndSend($data, $id, ['order' => $orderId], $this->context);

        static::assertInstanceOf(Email::class, $email);
        static::assertSame('Order ID: ' . $orderId, $email->getTextBody());
        static::assertSame('Order ID: ' . $orderId, $email->getHtmlBody());
        static::assertSame('Test', $email->getSubject());
        static::assertSame('Shopware', $email->getFrom()[0]->getName());
        static::assertSame('Test', $email->getTo()[0]->getName());
        static::assertSame('test@shopware.com', $email->getTo()[0]->getAddress());
    }

    /**
     * @param array<string, string> $availableEntities
     */
    private function createMailTemplate(
        array $availableEntities = [],
        string $contentHtml = 'test',
        string $contentPlain = 'test'
    ): string {
        $id = Uuid::randomHex();
        $this->mailTemplateRepository->create([
            [
                'id' => $id,
                'systemDefault' => false,
                'mailTemplateType' => [
                    'name' => 'Test',
                    'technicalName' => 'test',
                    'availableEntities' => $availableEntities,
                ],
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'subject' => 'Test',
                        'contentHtml' => $contentHtml,
                        'contentPlain' => $contentPlain,
                        'senderName' => 'Shopware',
                    ],
                ],
            ],
        ], $this->context);

        return $id;
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
