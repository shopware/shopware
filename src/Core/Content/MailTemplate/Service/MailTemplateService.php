<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\Mail\Service\MailAttachmentsConfig;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Content\MailTemplate\Validation\MailTemplateRenderError;
use Shopware\Core\Content\MailTemplate\Validation\MailTemplateRenderResultCollection;
use Shopware\Core\Content\MailTemplate\Validation\MailTemplateRenderSuccess;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Mime\Email;

/**
 * @internal
 */
#[Package('after-sales')]
class MailTemplateService
{
    /**
     * @param EntityRepository<MailTemplateCollection> $mailTemplateRepository
     */
    public function __construct(
        private readonly AbstractMailService $mailService,
        private readonly MailDataProvider $mailDataProvider,
        private readonly EntityRepository $mailTemplateRepository,
        private readonly StringTemplateRenderer $templateRenderer,
    ) {
    }

    public function loadTemplate(string $templateId, Context $context): MailTemplateEntity
    {
        $criteria = new Criteria([$templateId]);
        $criteria->addAssociation('mailTemplateType');
        $mailTemplate = $this->mailTemplateRepository->search($criteria, $context)->first();

        if ($mailTemplate === null) {
            throw MailTemplateException::templateNotFound();
        }

        \assert($mailTemplate instanceof MailTemplateEntity);

        return $mailTemplate;
    }

    /**
     * @param array<int|string,string> $templateContent
     * @param class-string<FlowEventAware> $flowEventClass
     */
    public function preview(array $templateContent, string $flowEventClass, Context $context, bool $strict = false): MailTemplateRenderResultCollection
    {
        $renderedResult = new MailTemplateRenderResultCollection();

        $templateData = $this->mailDataProvider->getTemplateData($flowEventClass, $context);

        if (!$strict) {
            $this->templateRenderer->enableTestMode();
        }

        foreach ($templateContent as $key => $value) {
            try {
                $renderedResult->set($key, new MailTemplateRenderSuccess($this->templateRenderer->render($value, $templateData, $context)));
            } catch (AdapterException $e) {
                $renderedResult->set($key, new MailTemplateRenderError($e->getMessage()));
            }
        }

        if (!$strict) {
            $this->templateRenderer->disableTestMode();
        }

        return $renderedResult;
    }

    /**
     * @param array<string, mixed> $data
     * @param class-string<FlowEventAware> $flowEventClass
     */
    public function getTemplateDataAndSend(array $data, string $flowEventClass, string $templateId, Context $context): ?Email
    {
        $mailTemplate = $this->loadTemplate($templateId, $context);

        $data['contentHtml'] ??= $mailTemplate->getContentHtml();
        $data['contentPlain'] ??= $mailTemplate->getContentPlain();
        $data['subject'] ??= $mailTemplate->getSubject();
        $data['senderName'] ??= $mailTemplate->getSenderName();

        $templateData = $this->mailDataProvider->getTemplateData($flowEventClass, $context);

        $extension = new MailSendSubscriberConfig(
            false,
            $data['documentIds'] ?? [],
            $data['mediaIds'] ?? [],
        );

        $data['attachmentsConfig'] = new MailAttachmentsConfig(
            $context,
            new MailTemplateEntity(),
            $extension,
            [],
            isset($templateData['order']) && $templateData['order'] instanceof OrderEntity ? $templateData['order']->getId() : null,
        );

        return $this->mailService->send($data, $context, $templateData);
    }
}
