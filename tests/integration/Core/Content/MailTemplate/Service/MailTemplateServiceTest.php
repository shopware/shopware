<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\MailTemplate\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeCollection;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Request\PreviewRequest;
use Shopware\Core\Content\MailTemplate\Request\SimulateRequest;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateService;
use Shopware\Core\Content\MailTemplate\Validation\MailTemplateRenderResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

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
            new PreviewRequest(
                mailTemplate: $mailTemplate,
                entityMapping: [],
                templateData: ['customName' => 'Shopware'],
            ),
            $this->context
        );

        static::assertEquals(MailTemplateRenderResult::success('Hello Shopware'), $rendered['subject']);
        static::assertEquals(MailTemplateRenderResult::success('Shopware'), $rendered['senderName']);
        static::assertEquals(MailTemplateRenderResult::success('<p>Hello Shopware</p>'), $rendered['contentHtml']);
        static::assertEquals(MailTemplateRenderResult::success('Hello Shopware'), $rendered['contentPlain']);
    }

    public function testSimulate(): void
    {
        $rendered = $this->mailTemplateService->simulate(
            new SimulateRequest(
                templateParts: ['contentHtml' => '<p>{{ order.id }}</p>'],
                eventName: 'checkout.order.placed',
            ),
            $this->context
        );

        static::assertInstanceOf(MailTemplateRenderResult::class, $rendered['contentHtml']);
        static::assertSame(MailTemplateRenderResult::TYPE_SUCCESS, $rendered['contentHtml']->getType());
        static::assertNotSame('', $rendered['contentHtml']->getContent());
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
