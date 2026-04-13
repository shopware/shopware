<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Request;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Payload\MailPayload;
use Shopware\Core\Content\Mail\Payload\MailPayloadFactory;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Request\GetDataAndSendRequestFactory;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(GetDataAndSendRequestFactory::class)]
class GetDataAndSendRequestFactoryTest extends TestCase
{
    private MailTemplateService&MockObject $mailTemplateService;

    private MailPayloadFactory&MockObject $mailPayloadFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailTemplateService = $this->createMock(MailTemplateService::class);
        $this->mailPayloadFactory = $this->createMock(MailPayloadFactory::class);
    }

    public function testMakeBuildsRequestAndFiltersUnknownEntities(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $mailPayload = new MailPayload(subject: 'payload subject');

        $request = new RequestDataBag([
            'mailTemplateId' => 'template-id',
            'entities' => [
                'order' => 'order-id',
                'customer' => 'customer-id',
            ],
            'templateData' => [
                'foo' => 'bar',
            ],
        ]);

        $this->mailTemplateService->expects($this->once())
            ->method('loadTemplate')
            ->with('template-id', $context)
            ->willReturn($mailTemplate);

        $this->mailPayloadFactory->expects($this->once())
            ->method('make')
            ->with(
                $request,
                [
                    'contentHtml' => '<p>html</p>',
                    'contentPlain' => 'plain',
                    'subject' => 'template subject',
                    'senderName' => 'template sender',
                ]
            )
            ->willReturn($mailPayload);

        $factory = new GetDataAndSendRequestFactory($this->mailTemplateService, $this->mailPayloadFactory);

        $result = $factory->make($request, $context);

        static::assertSame($mailTemplate, $result->mailTemplate);
        static::assertSame(['order' => 'order-id'], $result->entityMapping);
        static::assertSame(['foo' => 'bar'], $result->templateData);
        static::assertSame($mailPayload, $result->mailPayload);
    }

    public function testMakeKeepsEntitiesWhenMailTemplateTypeIsMissing(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = new MailTemplateEntity();
        $mailPayload = new MailPayload();

        $request = new RequestDataBag([
            'mailTemplateId' => 'template-id',
            'entities' => [
                'order' => 'order-id',
                'customer' => 'customer-id',
            ],
        ]);

        $this->mailTemplateService->method('loadTemplate')->willReturn($mailTemplate);
        $this->mailPayloadFactory->method('make')->willReturn($mailPayload);

        $factory = new GetDataAndSendRequestFactory($this->mailTemplateService, $this->mailPayloadFactory);

        $result = $factory->make($request, $context);

        static::assertSame(
            [
                'order' => 'order-id',
                'customer' => 'customer-id',
            ],
            $result->entityMapping
        );
    }

    private function createMailTemplate(): MailTemplateEntity
    {
        $mailTemplateType = new MailTemplateTypeEntity();
        $mailTemplateType->setAvailableEntities([
            'order' => 'order',
        ]);

        $mailTemplate = new MailTemplateEntity();
        $mailTemplate->setMailTemplateType($mailTemplateType);
        $mailTemplate->setContentHtml('<p>html</p>');
        $mailTemplate->setContentPlain('plain');
        $mailTemplate->setSubject('template subject');
        $mailTemplate->setSenderName('template sender');

        return $mailTemplate;
    }
}
