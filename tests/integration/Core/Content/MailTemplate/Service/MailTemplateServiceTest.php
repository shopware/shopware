<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\MailTemplate\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Payload\MailPayload;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeCollection;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Content\MailTemplate\Request\GetDataAndSendRequest;
use Shopware\Core\Content\MailTemplate\Request\PreviewRequest;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateService;
use Shopware\Core\Content\MailTemplate\Validation\MailTemplateRenderSuccess;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Mime\Email;

/**
 * @internal
 */
#[Package('after-sales')]
class MailTemplateServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private MailTemplateService $mailTemplateService;

    private Context $context;

    /**
     * @var EntityRepository<MailTemplateCollection>
     */
    private EntityRepository $mailTemplateRepository;

    protected function setUp(): void
    {
        $this->mailTemplateRepository = static::getContainer()->get('mail_template.repository');
        $this->mailTemplateService = static::getContainer()->get(MailTemplateService::class);
        $this->context = Context::createDefaultContext();
    }

    public function testLoadTemplateNoTemplateFound(): void
    {
        $this->expectExceptionObject(MailTemplateException::templateNotFound());

        $this->mailTemplateService->loadTemplate(Uuid::randomHex(), $this->context);
    }

    public function testLoadTemplate(): void
    {
        $mailTemplate = $this->createSimpleMailTemplate();

        $loadedTemplate = $this->mailTemplateService->loadTemplate($mailTemplate->getId(), $this->context);

        static::assertSame($mailTemplate->getId(), $loadedTemplate->getId());
        static::assertSame('Hello {{ customName }}', $loadedTemplate->getSubject());
        static::assertSame('<p>Hello {{ customName }}</p>', $loadedTemplate->getContentHtml());
        static::assertSame('Hello {{ customName }}', $loadedTemplate->getContentPlain());
        static::assertSame('Shopware', $loadedTemplate->getSenderName());
    }

    public function testPreviewRendersTemplateData(): void
    {
        $mailTemplate = $this->createSimpleMailTemplate();

        $rendered = $this->mailTemplateService->preview(
            new PreviewRequest($mailTemplate, [], ['customName' => 'Shopware']),
            $this->context
        );

        static::assertEquals(new MailTemplateRenderSuccess('Hello Shopware'), $rendered->get('subject'));
        static::assertEquals(new MailTemplateRenderSuccess('Shopware'), $rendered->get('senderName'));
        static::assertEquals(new MailTemplateRenderSuccess('<p>Hello Shopware</p>'), $rendered->get('contentHtml'));
        static::assertEquals(new MailTemplateRenderSuccess('Hello Shopware'), $rendered->get('contentPlain'));
    }

    public function testGetTemplateDataAndSend(): void
    {
        $mailTemplate = $this->createSimpleMailTemplate();

        $email = $this->mailTemplateService->getTemplateDataAndSend(
            new GetDataAndSendRequest(
                $mailTemplate,
                [],
                ['customName' => 'Shopware'],
                new MailPayload(
                    recipients: ['test@example.com' => 'Test'],
                    contentHtml: $mailTemplate->getContentHtml(),
                    contentPlain: $mailTemplate->getContentPlain(),
                    subject: $mailTemplate->getSubject(),
                    senderName: $mailTemplate->getSenderName(),
                )
            ),
            $this->context
        );

        static::assertInstanceOf(Email::class, $email);
        static::assertSame('Hello Shopware', $email->getSubject());
        static::assertSame('Shopware', $email->getFrom()[0]->getName());
        static::assertSame('Test', $email->getTo()[0]->getName());
        static::assertSame('test@example.com', $email->getTo()[0]->getAddress());
        static::assertSame('Hello Shopware', $email->getTextBody());
        static::assertSame('<p>Hello Shopware</p>', $email->getHtmlBody());
    }

    public function testSimulate(): void
    {
        $rendered = $this->mailTemplateService->simulate(
            ['contentHtml' => '<p>{{ order.id }}</p>'],
            'checkout.order.placed',
            $this->context
        );

        static::assertInstanceOf(MailTemplateRenderSuccess::class, $rendered->get('contentHtml'));
        static::assertNotSame('', $rendered->get('contentHtml')?->getContent());
    }

    public function testGetAvailableVariables(): void
    {
        $variables = $this->mailTemplateService->getAvailableVariables('checkout.order.placed', $this->context, 'order');

        static::assertIsArray($variables);
        static::assertNotSame([], $variables);
        static::assertContains('lineItems', array_column($variables, 'fieldName'));
    }

    private function createSimpleMailTemplate(): MailTemplateEntity
    {
        $typeCriteria = new Criteria();
        $typeCriteria->setLimit(1);

        /** @var EntityRepository<MailTemplateTypeCollection> $mailTemplateTypeRepository */
        $mailTemplateTypeRepository = static::getContainer()->get('mail_template_type.repository');
        $mailTemplateType = $mailTemplateTypeRepository->search($typeCriteria, $this->context)->first();

        static::assertInstanceOf(MailTemplateTypeEntity::class, $mailTemplateType);

        $mailTemplateId = Uuid::randomHex();

        $this->mailTemplateRepository->create([[
            'id' => $mailTemplateId,
            'mailTemplateTypeId' => $mailTemplateType->getId(),
            'subject' => 'Hello {{ customName }}',
            'senderName' => 'Shopware',
            'contentHtml' => '<p>Hello {{ customName }}</p>',
            'contentPlain' => 'Hello {{ customName }}',
        ]], $this->context);

        $mailTemplate = $this->mailTemplateRepository->search(
            new Criteria([$mailTemplateId]),
            $this->context
        )->first();

        static::assertInstanceOf(MailTemplateEntity::class, $mailTemplate);

        return $mailTemplate;
    }
}
