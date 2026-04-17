<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Mail\Payload\MailPayload;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\Mail\Service\MailAttachmentsConfig;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Content\MailTemplate\Request\GetDataAndSendRequest;
use Shopware\Core\Content\MailTemplate\Request\PreviewRequest;
use Shopware\Core\Content\MailTemplate\Request\SimulateRequest;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Content\MailTemplate\Validation\MailTemplateRenderResult;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Struct\Struct;
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
        private readonly MailDataSimulator $mailDataSimulator,
        private readonly MailTemplateContentBuilder $mailTemplateContentBuilder,
    ) {
    }

    public function loadTemplate(string $templateId, Context $context): MailTemplateEntity
    {
        $criteria = new Criteria([$templateId]);
        $criteria->addAssociation('mailTemplateType');
        $criteria->addAssociation('media.media');
        $mailTemplate = $this->mailTemplateRepository->search($criteria, $context)->first();

        if ($mailTemplate === null) {
            throw MailTemplateException::templateNotFound();
        }

        \assert($mailTemplate instanceof MailTemplateEntity);

        return $mailTemplate;
    }

    /**
     * @return array<int|string, MailTemplateRenderResult>
     */
    public function simulate(
        SimulateRequest $simulateRequest,
        Context $context,
    ): array {
        $renderedResult = [];

        $templateData = $this->mailDataSimulator->getTemplateData($simulateRequest->eventName, $context, $simulateRequest->salesChannel);

        if (!$simulateRequest->strictRendering) {
            $this->templateRenderer->enableTestMode();
        }

        foreach ($simulateRequest->templateParts as $key => $content) {
            try {
                $rendered = $this->templateRenderer->render($content, $templateData, $context, false);

                $renderedResult[$key] = MailTemplateRenderResult::success($rendered);
            } catch (\Throwable $e) {
                $renderedResult[$key] = MailTemplateRenderResult::error($e->getMessage());
            }
        }

        if (!$simulateRequest->strictRendering) {
            $this->templateRenderer->disableTestMode();
        }

        return $renderedResult;
    }

    /**
     * @return array<int|string, MailTemplateRenderResult>
     */
    public function preview(
        PreviewRequest $request,
        Context $context,
    ): array {
        $renderedResult = [];

        $templateData = $this->mailDataProvider->getTemplateData(
            $request->mailTemplate,
            $request->entityMapping,
            $context,
            $request->templateData
        );

        if (!$request->strictRendering) {
            $this->templateRenderer->enableTestMode();
        }

        $templateContent = $this->getTemplateContent($request->mailTemplate);

        if ($request->includeHeaderFooter) {
            $templateContent = array_replace(
                $templateContent,
                $this->mailTemplateContentBuilder->build([
                    'contentPlain' => $templateContent['contentPlain'],
                    'contentHtml' => $templateContent['contentHtml'],
                ], $request->salesChannel)
            );
        }

        foreach ($templateContent as $key => $value) {
            try {
                $rendered = $this->templateRenderer->render($value, $templateData, $context, false);

                $renderedResult[$key] = MailTemplateRenderResult::success($rendered);
            } catch (\Throwable $e) {
                $renderedResult[$key] = MailTemplateRenderResult::error($e->getMessage());
            }
        }

        if (!$request->strictRendering) {
            $this->templateRenderer->disableTestMode();
        }

        return $renderedResult;
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
            $mailPayload->documentIds ?? [],
            $mailPayload->mediaIds ?? [],
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

        return $this->mailService->send($data, $context, $templateData);
    }

    /**
     * @return array<string,int|string|bool>[]
     */
    public function getAvailableVariables(
        string $flowEvent,
        Context $context,
        ?string $parentVariablePath = '',
    ): array {
        $templateData = $this->mailDataSimulator->getTemplateData($flowEvent, $context);

        if ($parentVariablePath === null || $parentVariablePath === '') {
            return \array_map(
                fn ($fieldName) => [
                    'fieldName' => $fieldName,
                    'hasChildren' => \is_object($templateData[$fieldName])
                        || (\is_array($templateData[$fieldName]) && $templateData[$fieldName] !== []),
                ],
                \array_keys($templateData)
            );
        }

        $fieldPathParts = \explode('.', $parentVariablePath);

        foreach ($fieldPathParts as $fieldPathPart) {
            if ($templateData instanceof Collection) {
                if ($fieldPathPart !== 'first') {
                    return [];
                }

                $templateData = $templateData->first();

                continue;
            }

            if ($templateData instanceof Struct) {
                $templateData = $templateData->jsonSerialize();
            }

            if (!\is_array($templateData) || !\array_key_exists($fieldPathPart, $templateData)) {
                return [];
            }

            $templateData = $templateData[$fieldPathPart];
        }

        if ($templateData instanceof Collection) {
            $first = $templateData->first();

            if ($first === null) {
                return [];
            }

            return [[
                'fieldName' => 'first',
                'hasChildren' => \is_object($first) || (\is_array($first) && $first !== []),
            ]];
        }

        if ($templateData instanceof Struct) {
            $templateData = $templateData->jsonSerialize();
        }

        if (!\is_array($templateData)) {
            return [];
        }

        return \array_map(
            fn ($fieldName) => [
                'fieldName' => $fieldName,
                'hasChildren' => \is_object($templateData[$fieldName])
                    || (\is_array($templateData[$fieldName]) && $templateData[$fieldName] !== []),
            ],
            \array_keys($templateData)
        );
    }

    /**
     * @return array<int|string,string>
     */
    private function getTemplateContent(MailTemplateEntity $mailTemplate): array
    {
        return [
            'subject' => $mailTemplate->getSubject() ?? '',
            'senderName' => $mailTemplate->getSenderName() ?? '',
            'contentHtml' => $mailTemplate->getContentHtml() ?? '',
            'contentPlain' => $mailTemplate->getContentPlain() ?? '',
        ];
    }
}
