<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Request;

use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\SalesChannelProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

/**
 * @internal
 */
#[Package('after-sales')]
readonly class SimulateRequestFactory extends AbstractMailTemplateRequestFactory
{
    public function __construct(
        private SalesChannelProvider $salesChannelProvider,
    ) {
    }

    public function make(RequestDataBag $request, Context $context): SimulateRequest
    {
        $templateParts = $this->normalizeArrayParameter('templateParts', $request->get('templateParts'));

        $eventName = $this->normalizeStringParameter('eventName', $request->get('eventName'));
        if ($eventName === null) {
            throw MailTemplateException::invalidRequestParameterType('eventName', 'string', get_debug_type($eventName));
        }

        $salesChannel = null;
        $salesChannelId = $this->normalizeStringParameter('salesChannelId', $request->get('salesChannelId'));
        if ($salesChannelId !== null) {
            $salesChannel = $this->salesChannelProvider->getData($salesChannelId, $context);
            if ($salesChannel === null) {
                throw MailTemplateException::invalidSalesChannelId($salesChannelId);
            }
        }

        $strictRendering = $this->normalizeBoolParameter('strictRendering', $request->get('strictRendering', true));

        return new SimulateRequest($templateParts, $eventName, $salesChannel, $strictRendering);
    }
}
