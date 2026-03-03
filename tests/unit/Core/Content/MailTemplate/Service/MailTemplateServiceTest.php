<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Service\MailDataProvider;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateService;
use Shopware\Core\Content\MailTemplate\Validation\MailTemplateRenderError;
use Shopware\Core\Content\MailTemplate\Validation\MailTemplateRenderSuccess;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
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

        $rendered = $mailTemplateService->preview(['content' => 'foo'], ContactFormEvent::class, Context::createDefaultContext());

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

        $rendered = $mailTemplateService->preview(['content' => 'foo'], ContactFormEvent::class, Context::createDefaultContext());

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

        $email = $mailTemplateService->getTemplateDataAndSend([], ContactFormEvent::class, $mailTemplate->getId(), $context);

        static::assertNull($email);
    }
}
