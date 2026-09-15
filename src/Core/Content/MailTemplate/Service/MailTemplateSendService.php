<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Mail\Payload\MailPayload;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\Mail\Service\MailAttachmentsConfig;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Request\GetDataAndSendRequest;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Symfony\Component\Mime\Email;

/**
 * @internal
 */
#[Package('after-sales')]
class MailTemplateSendService
{
    public function __construct(
        private readonly AbstractMailService $mailService,
        private readonly MailDataProvider $mailDataProvider,
        private readonly AbstractTranslator $translator,
        private readonly LanguageLocaleCodeProvider $languageLocaleProvider,
    ) {
    }

    public function getTemplateDataAndSend(
        GetDataAndSendRequest $request,
        Context $context,
    ): ?Email {
        $templateData = $this->mailDataProvider->getTemplateData(
            $request->mailTemplate,
            $request->entityMapping,
            $context,
            $request->templateData
        );

        return $this->send($request->mailPayload, $context, $templateData, $request->mailTemplate);
    }

    /**
     * @param array<string|int,mixed> $templateData
     */
    public function send(
        MailPayload $mailPayload,
        Context $context,
        array $templateData,
        ?MailTemplateEntity $mailTemplate = null,
    ): ?Email {
        $data = $mailPayload->toArray();

        $extension = new MailSendSubscriberConfig(
            false,
            $mailPayload->documentIds,
            $mailPayload->mediaIds,
        );

        $orderId = null;
        if (\array_key_exists('order', $templateData)) {
            if (\is_array($templateData['order'])) {
                $orderId = $templateData['order']['id'] ?? null;
            } elseif ($templateData['order'] instanceof OrderEntity) {
                $orderId = $templateData['order']->getId();
            }
        }

        $data['attachmentsConfig'] = new MailAttachmentsConfig(
            $context,
            $mailTemplate ?? new MailTemplateEntity(),
            $extension,
            [],
            $orderId,
        );

        $injected = $this->injectTranslator($context, $mailPayload->salesChannelId);

        try {
            return $this->mailService->send($data, $context, $templateData);
        } finally {
            if ($injected) {
                $this->translator->resetInjection();
            }
        }
    }

    private function injectTranslator(Context $context, ?string $salesChannelId): bool
    {
        if ($salesChannelId === null) {
            return false;
        }

        if ($this->translator->getSnippetSetId() !== null) {
            return false;
        }

        $this->translator->injectSettings(
            $salesChannelId,
            $context->getLanguageId(),
            $this->languageLocaleProvider->getLocaleForLanguageId($context->getLanguageId()),
            $context
        );

        return true;
    }
}
