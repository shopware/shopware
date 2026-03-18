<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\Mail\Service\MailAttachmentsConfig;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Content\MailTemplate\Service\MailDataProvider;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateService;
use Shopware\Core\Content\MailTemplate\Validation\MailTemplateRenderError;
use Shopware\Core\Content\MailTemplate\Validation\MailTemplateRenderSuccess;
use Shopware\Core\Content\MeasurementSystem\MeasurementUnits;
use Shopware\Core\Content\Product\SalesChannel\Review\Event\ReviewFormEvent;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(MailTemplateService::class)]
#[Package('after-sales')]
class MailTemplateServiceTest extends TestCase
{
    public function testLoadTemplate(): void
    {
        $mailTemplate = new MailTemplateEntity();
        $mailTemplate->setId(Uuid::randomHex());
        $mailTemplate->setContentHtml('html');

        $mailService = $this->createMock(AbstractMailService::class);
        $mailDataProvider = $this->createMock(MailDataProvider::class);
        /** @var StaticEntityRepository<MailTemplateCollection> $mailTemplateRepository */
        $mailTemplateRepository = new StaticEntityRepository([new MailTemplateCollection([$mailTemplate])]);
        $stringTemplateRenderer = $this->createMock(StringTemplateRenderer::class);

        $mailTemplateService = new MailTemplateService(
            $mailService,
            $mailDataProvider,
            $mailTemplateRepository,
            $stringTemplateRenderer
        );

        $loadedMailTemplate = $mailTemplateService->loadTemplate($mailTemplate->getId(), Context::createDefaultContext());

        static::assertSame($mailTemplate, $loadedMailTemplate);
    }

    public function testLoadUnknownTemplate(): void
    {
        $mailTemplate = new MailTemplateEntity();
        $mailTemplate->setId(Uuid::randomHex());
        $mailTemplate->setContentHtml('html');

        $mailService = $this->createMock(AbstractMailService::class);
        $mailDataProvider = $this->createMock(MailDataProvider::class);
        /** @var StaticEntityRepository<MailTemplateCollection> $mailTemplateRepository */
        $mailTemplateRepository = new StaticEntityRepository([new MailTemplateCollection()]);
        $stringTemplateRenderer = $this->createMock(StringTemplateRenderer::class);

        $mailTemplateService = new MailTemplateService(
            $mailService,
            $mailDataProvider,
            $mailTemplateRepository,
            $stringTemplateRenderer
        );

        static::expectExceptionObject(MailTemplateException::templateNotFound());

        $mailTemplateService->loadTemplate($mailTemplate->getId(), Context::createDefaultContext());
    }

    public function testPreview(): void
    {
        $mailService = $this->createMock(AbstractMailService::class);
        $mailDataProvider = $this->createMock(MailDataProvider::class);
        $mailDataProvider->method('getTemplateData')->willReturn([]);
        /** @var StaticEntityRepository<MailTemplateCollection> $mailTemplateRepository */
        $mailTemplateRepository = new StaticEntityRepository([]);
        $stringTemplateRenderer = $this->createMock(StringTemplateRenderer::class);
        $stringTemplateRenderer->method('render')->willReturn('bar');

        $mailTemplateService = new MailTemplateService(
            $mailService,
            $mailDataProvider,
            $mailTemplateRepository,
            $stringTemplateRenderer
        );

        $rendered = $mailTemplateService->preview(['content' => 'foo'], Context::createDefaultContext(), false,ContactFormEvent::class);

        static::assertCount(1, $rendered);
        static::assertEquals(new MailTemplateRenderSuccess('bar'), $rendered->get('content'));
    }

    public function testPreviewThrowsException(): void
    {
        $mailService = $this->createMock(AbstractMailService::class);
        $mailDataProvider = $this->createMock(MailDataProvider::class);
        /** @var StaticEntityRepository<MailTemplateCollection> $mailTemplateRepository */
        $mailTemplateRepository = new StaticEntityRepository([]);
        $stringTemplateRenderer = $this->createMock(StringTemplateRenderer::class);
        $stringTemplateRenderer->method('render')->willThrowException(AdapterException::renderingTemplateFailed('Some error message'));

        $mailTemplateService = new MailTemplateService(
            $mailService,
            $mailDataProvider,
            $mailTemplateRepository,
            $stringTemplateRenderer
        );

        $rendered = $mailTemplateService->preview(['content' => 'foo'], Context::createDefaultContext(), false, ContactFormEvent::class);

        static::assertCount(1, $rendered);
        static::assertEquals(new MailTemplateRenderError('Failed rendering string template using Twig: Some error message'), $rendered->get('content'));
    }

    public function testGetDataAndSend(): void
    {
        $context = Context::createDefaultContext();

        $mailTemplate = new MailTemplateEntity();
        $mailTemplate->setId(Uuid::randomHex());
        $mailTemplate->setContentHtml('html');
        $mailTemplate->setContentPlain('plain');
        $mailTemplate->setSubject('subject');
        $mailTemplate->setSenderName('sender name');

        $mailService = $this->createMock(AbstractMailService::class);
        $mailService->expects($this->once())->method('send')->willReturn(null);

        $mailDataProvider = $this->createMock(MailDataProvider::class);
        $mailDataProvider->method('getTemplateData')->willReturn([]);

        /** @var StaticEntityRepository<MailTemplateCollection> $mailTemplateRepository */
        $mailTemplateRepository = new StaticEntityRepository([new MailTemplateCollection([$mailTemplate])]);
        $stringTemplateRenderer = $this->createMock(StringTemplateRenderer::class);

        $mailTemplateService = new MailTemplateService(
            $mailService,
            $mailDataProvider,
            $mailTemplateRepository,
            $stringTemplateRenderer
        );

        $email = $mailTemplateService->getTemplateDataAndSend([], $context, ContactFormEvent::class);

        static::assertNull($email);
    }

    public function testSendSuccess(): void
    {
        $data = (new RequestDataBag([
            'id' => 'random',
            'mailTemplateData' => [
                'order' => [
                    'id' => Uuid::randomHex(),
                ],
            ],
            'documentIds' => ['1'],
        ]))->all();

        $mailService = $this->createMock(AbstractMailService::class);
        $mailService->expects($this->once())
            ->method('send')
            ->with(
                static::callback(function (array $data) {
                    static::assertArrayHasKey('attachmentsConfig', $data);
                    static::assertInstanceOf(MailAttachmentsConfig::class, $data['attachmentsConfig']);

                    return true;
                }),
                static::anything(),
                static::anything()
            );

        $mailDataProvider = $this->createMock(MailDataProvider::class);
        $mailDataProvider->method('getTemplateData')->willReturn([]);

        /** @var StaticEntityRepository<MailTemplateCollection> $mailTemplateRepository */
        $mailTemplateRepository = new StaticEntityRepository([]);
        $stringTemplateRenderer = $this->createMock(StringTemplateRenderer::class);

        $mailTemplateService = new MailTemplateService(
            $mailService,
            $mailDataProvider,
            $mailTemplateRepository,
            $stringTemplateRenderer
        );

        $mailTemplateService->send($data, Context::createDefaultContext(), []);
    }

    /**
     * @param array<array{fieldName: string, hasChildren: bool}> $expected
     */
    #[DataProvider('fieldPathProvider')]
    public function testAvailableVariables(string $fieldPath, array $expected): void
    {
        $mailService = $this->createMock(AbstractMailService::class);

        $mailDataProvider = $this->createMock(MailDataProvider::class);
        $mailDataProvider->method('getTemplateData')->willReturn([
            'foo' => 'value',
            'bar' => [
                'foobar' => 'value',
                'baz' => [
                    'key' => 'value',
                ],
                'struct' => MeasurementUnits::createDefaultUnits(),
            ],
            'topLevelStruct' => MeasurementUnits::createDefaultUnits(),
        ]);

        /** @var StaticEntityRepository<MailTemplateCollection> $mailTemplateRepository */
        $mailTemplateRepository = new StaticEntityRepository([new MailTemplateCollection()]);
        $stringTemplateRenderer = $this->createMock(StringTemplateRenderer::class);

        $mailTemplateService = new MailTemplateService(
            $mailService,
            $mailDataProvider,
            $mailTemplateRepository,
            $stringTemplateRenderer
        );

        $result = $mailTemplateService->availableVariables($fieldPath, Context::createDefaultContext(), ReviewFormEvent::class);

        static::assertSame($expected, $result);
    }

    public static function fieldPathProvider(): \Generator
    {
        yield 'empty field path' => [
            'fieldPath' => '',
            'expected' => [
                [
                    'fieldName' => 'foo',
                    'hasChildren' => false,
                ],
                [
                    'fieldName' => 'bar',
                    'hasChildren' => true,
                ],
                [
                    'fieldName' => 'topLevelStruct',
                    'hasChildren' => true,
                ],
            ],
        ];

        yield 'valid field path' => [
            'fieldPath' => 'bar',
            'expected' => [
                [
                    'fieldName' => 'foobar',
                    'hasChildren' => false,
                ],
                [
                    'fieldName' => 'baz',
                    'hasChildren' => true,
                ],
                [
                    'fieldName' => 'struct',
                    'hasChildren' => true,
                ],
            ],
        ];

        yield 'valid field path on element without children' => [
            'fieldPath' => 'foo',
            'expected' => [],
        ];

        yield 'nested field path' => [
            'fieldPath' => 'bar.baz',
            'expected' => [
                [
                    'fieldName' => 'key',
                    'hasChildren' => false,
                ],
            ],
        ];

        yield 'unknown field path' => [
            'fieldPath' => 'unknown',
            'expected' => [],
        ];

        yield 'field path to struct' => [
            'fieldPath' => 'bar.struct',
            'expected' => [
                [
                    'fieldName' => 'extensions',
                    'hasChildren' => false,
                ],
                [
                    'fieldName' => 'system',
                    'hasChildren' => false,
                ],
                [
                    'fieldName' => 'units',
                    'hasChildren' => true,
                ],
            ],
        ];

        yield 'access struct property' => [
            'fieldPath' => 'bar.struct.units',
            'expected' => [
                [
                    'fieldName' => 'length',
                    'hasChildren' => false,
                ],
                [
                    'fieldName' => 'weight',
                    'hasChildren' => false,
                ],
            ],
        ];
    }

    public function testAvailableVariablesWithEmptyTemplateData(): void
    {
        $mailService = $this->createMock(AbstractMailService::class);

        $mailDataProvider = $this->createMock(MailDataProvider::class);
        $mailDataProvider->method('getTemplateData')->willReturn([]);

        /** @var StaticEntityRepository<MailTemplateCollection> $mailTemplateRepository */
        $mailTemplateRepository = new StaticEntityRepository([new MailTemplateCollection()]);
        $stringTemplateRenderer = $this->createMock(StringTemplateRenderer::class);

        $mailTemplateService = new MailTemplateService(
            $mailService,
            $mailDataProvider,
            $mailTemplateRepository,
            $stringTemplateRenderer
        );

        $result = $mailTemplateService->availableVariables('foobar.foo.bar', Context::createDefaultContext(), ReviewFormEvent::class);

        static::assertSame([], $result);
    }
}
