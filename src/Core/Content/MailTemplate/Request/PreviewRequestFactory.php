<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Request;

use Shopware\Core\Content\MailTemplate\Service\MailTemplateService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

/**
 * @internal
 */
#[Package('after-sales')]
readonly class PreviewRequestFactory
{
    public function __construct(private MailTemplateService $mailTemplateService)
    {
    }

    public function make(RequestDataBag $request, Context $context): PreviewRequest
    {
        $templateId = $request->getString('mailTemplateId');

        $mailTemplate = $this->mailTemplateService->loadTemplate($templateId, $context);

        $entitiesDataBag = $request->get('entities', new DataBag());
        \assert($entitiesDataBag instanceof DataBag);
        $entities = $entitiesDataBag->all();

        $mailTemplateType = $mailTemplate->getMailTemplateType();

        if ($mailTemplateType !== null) {
            foreach ($entities as $key => $id) {
                $availableEntities = $mailTemplateType->getAvailableEntities();

                if (!\array_key_exists($key, $availableEntities)) {
                    unset($entities[$key]); // TODO Do we need this? Or do we want to throw instead?
                }
            }
        }

        $templateDataDataBag = $request->get('templateData', new DataBag());
        \assert($templateDataDataBag instanceof DataBag);
        $templateData = $templateDataDataBag->all();

        return new PreviewRequest(
            $mailTemplate,
            $entities,
            $templateData
        );
    }
}
