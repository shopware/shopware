<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Request;

use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateService;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\SalesChannelProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

/**
 * @internal
 */
#[Package('after-sales')]
readonly class PreviewRequestFactory extends AbstractMailTemplateRequestFactory
{
    public function __construct(
        private MailTemplateService $mailTemplateService,
        private SalesChannelProvider $salesChannelProvider,
    ) {
    }

    public function make(RequestDataBag $request, Context $context): PreviewRequest
    {
        $templateId = $request->getString('mailTemplateId');
        $mailTemplate = $this->mailTemplateService->loadTemplate($templateId, $context);

        $entities = $this->normalizeArrayParameter('entities', $request->get('entities', []));
        $entities = $this->filterAvailableEntities($entities, $mailTemplate);

        $templateData = $this->normalizeArrayParameter('templateData', $request->get('templateData', []));

        $salesChannel = null;
        $salesChannelId = $this->normalizeStringParameter('salesChannelId', $request->get('salesChannelId'));
        if ($salesChannelId !== null) {
            $salesChannel = $this->salesChannelProvider->getData($salesChannelId, $context);
            if ($salesChannel === null) {
                throw MailTemplateException::invalidSalesChannelId($salesChannelId);
            }
        }

        $includeHeaderFooter = $this->normalizeBoolParameter('includeHeaderFooter', $request->get('includeHeaderFooter', false));
        $strictRendering = $this->normalizeBoolParameter('strictRendering', $request->get('strictRendering', false));

        return new PreviewRequest(
            $mailTemplate,
            $salesChannel,
            $entities,
            $templateData,
            $includeHeaderFooter,
            $strictRendering,
        );
    }

}
