<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\Mail\Service\MailAttachmentsConfig;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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

    public function preview(string $templateId, array $entities, Context $context): string
    {
        $criteria = new Criteria([$templateId]);
        $criteria->addAssociation('mailTemplateType');
        $mailTemplate = $this->mailTemplateRepository->search($criteria, $context)->first();

        $template = $mailTemplate->getContentHtml();
        if (!\is_string($template)) {
            throw MailTemplateException::invalidMailTemplateContent();
        }

        $templateData = $this->mailDataProvider->getTemplateData($mailTemplate, $entities, $context);

        $this->templateRenderer->enableTestMode();
        $renderedTemplate = $this->templateRenderer->render($template, $templateData, $context);
        $this->templateRenderer->disableTestMode();

        return $renderedTemplate;
    }

    public function getTemplateDataAndSend(string $templateId, array $entities, Context $context): ?Email
    {
        $criteria = new Criteria([$templateId]);
        $criteria->addAssociation('mailTemplateType');
        $mailTemplate = $this->mailTemplateRepository->search($criteria, $context)->first();

        $data = [];
        $data['contentHtml'] = $mailTemplate->getContentHtml();
        $data['contentPlain'] = $mailTemplate->getContentPlain();
        $data['subject'] = $mailTemplate->getSubject();
        $data['senderName'] = $mailTemplate->getSenderName();

        $templateData = $this->mailDataProvider->getTemplateData($mailTemplate, $entities, $context);

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
            $mailTemplateData['order']['id'] ?? null,
        );

        return $this->mailService->send($data, $context, $templateData);
    }
}
