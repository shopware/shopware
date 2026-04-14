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

        $entities = $this->normalizeArrayParameter($request->get('entities', []));

        $mailTemplateType = $mailTemplate->getMailTemplateType();

        if ($mailTemplateType !== null) {
            $availableEntities = $mailTemplateType->getAvailableEntities() ?? [];

            foreach ($entities as $key => $id) {
                if (!\array_key_exists($key, $availableEntities)) {
                    unset($entities[$key]); // TODO Do we need this? Or do we want to throw instead?
                }
            }
        }

        $templateData = $this->normalizeArrayParameter($request->get('templateData', []));

        return new PreviewRequest(
            $mailTemplate,
            $entities,
            $templateData
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
