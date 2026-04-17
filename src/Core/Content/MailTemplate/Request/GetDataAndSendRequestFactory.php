<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Request;

use Shopware\Core\Content\Mail\Payload\MailPayloadFactory;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

/**
 * @internal
 */
#[Package('after-sales')]
readonly class GetDataAndSendRequestFactory extends AbstractMailTemplateRequestFactory
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

        $entities = $this->normalizeArrayParameter('entities', $request->get('entities', []));
        $entities = $this->filterAvailableEntities($entities, $mailTemplate);

        $templateData = $this->normalizeArrayParameter('templateData', $request->get('templateData', []));

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
}
