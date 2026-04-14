<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Request;

use Shopware\Core\Content\Mail\Payload\MailPayloadFactory;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

/**
 * @internal
 */
#[Package('after-sales')]
readonly class GetDataAndSendRequestFactory
{
    public function __construct(
        private MailTemplateService $mailTemplateService,
        private MailPayloadFactory $mailPayloadFactory,
    ) {
    }

    public function make(RequestDataBag $request, Context $context): GetDataAndSendRequest
    {
        $templateId = $request->getString('mailTemplateId');
        $mailTemplate = $this->mailTemplateService->loadTemplate($templateId, $context);

        $entities = $this->normalizeArrayParameter($request->get('entities', []));

        $mailTemplateType = $mailTemplate->getMailTemplateType();

        if ($mailTemplateType !== null) {
            $availableEntities = $mailTemplateType->getAvailableEntities() ?? [];

            foreach ($entities as $key => $id) {
                if (!\array_key_exists($key, $availableEntities)) {
                    unset($entities[$key]);
                }
            }
        }

        $templateData = $this->normalizeArrayParameter($request->get('templateData', []));

        return new GetDataAndSendRequest(
            mailTemplate: $mailTemplate,
            entityMapping: $entities,
            templateData: $templateData,
            mailPayload: $this->mailPayloadFactory->make(
                $request,
                [
                    'contentHtml' => $mailTemplate->getContentHtml(),
                    'contentPlain' => $mailTemplate->getContentPlain(),
                    'subject' => $mailTemplate->getSubject(),
                    'senderName' => $mailTemplate->getSenderName(),
                ],
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeArrayParameter(mixed $value): array
    {
        if ($value instanceof DataBag) {
            return $value->all();
        }

        if (\is_array($value)) {
            return $value;
        }

        return [];
    }
}
