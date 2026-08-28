<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Request\Resolver;

use Shopware\Core\Content\Mail\Payload\MailPayloadFactory;
use Shopware\Core\Content\MailTemplate\Request\GetDataAndSendRequest;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @internal
 */
#[Package('after-sales')]
readonly class GetDataAndSendRequestResolver extends AbstractMailTemplateRequestResolver implements ValueResolverInterface
{
    public function __construct(
        private MailTemplateService $mailTemplateService,
        private MailPayloadFactory $mailPayloadFactory,
    ) {
    }

    /**
     * @return \Generator<GetDataAndSendRequest>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        if ($argument->getType() !== GetDataAndSendRequest::class) {
            return;
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        if (!$context instanceof Context) {
            return;
        }

        yield $this->make(new RequestDataBag($request->request->all()), $context);
    }

    private function make(RequestDataBag $request, Context $context): GetDataAndSendRequest
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
