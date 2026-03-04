<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

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
    public function getTemplateDataAndSend(array $data, string $flowEventClass, Context $context): ?Email
    {
        $templateData = $this->mailDataProvider->getTemplateData($flowEventClass, $context);

        return $this->send($data, $context, $templateData);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string|int,mixed> $templateData
     */
    public function send(array $data, Context $context, array $templateData): ?Email
    {
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
            $templateData['order']['id'] ?? null,
        );

        return $this->mailService->send($data, $context, $templateData);
    }

    /**
     * @param class-string<FlowEventAware> $flowEventClass
     *
     * @return array<string,int|string|bool>[]
     */
    public function availableVariables(string $flowEventClass, string $fieldPath, Context $context): array
    {
        $templateData = $this->mailDataProvider->getTemplateData($flowEventClass, $context);

        if ($fieldPath === '') {
            return \array_map(
                fn ($fieldName) => ['fieldName' => $fieldName, 'hasChildren' => \is_array($templateData[$fieldName]) && $templateData[$fieldName] !== []],
                \array_keys($templateData)
            );
        }

        $fieldPathParts = \explode('.', $fieldPath);

        foreach ($fieldPathParts as $fieldPathPart) {
            if (!\array_key_exists($fieldPathPart, $templateData)) {
                return [];
            }

            $templateData = $templateData[$fieldPathPart];
        }

        return \array_map(
            fn ($fieldName) => ['fieldName' => $fieldName, 'hasChildren' => \is_array($templateData[$fieldName]) && $templateData[$fieldName] !== []],
            \array_keys($templateData)
        );
    }
}
