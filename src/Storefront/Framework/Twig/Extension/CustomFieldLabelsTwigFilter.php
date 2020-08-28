<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Extension;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class CustomFieldLabelsTwigFilter extends AbstractExtension
{
    /**
     * @var CustomFieldService
     */
    private $customFieldService;

    public function __construct(CustomFieldService $customFieldService)
    {
        $this->customFieldService = $customFieldService;
    }

    public function getFilters()
    {
        return [
            new TwigFilter('sw_custom_field_labels', [$this, 'getCustomFieldLabels'], ['needs_context' => true]),
        ];
    }

    public function getCustomFieldLabels($twigContext, array $customFieldNames): array
    {
        if (!array_key_exists('context', $twigContext)
            || (
                !$twigContext['context'] instanceof Context
                && !$twigContext['context'] instanceof SalesChannelContext
            )
        ) {
            throw new \InvalidArgumentException('Error while processing Twig currency filter. No context given.');
        }

        $context = $twigContext['context'];
        if ($twigContext['context'] instanceof SalesChannelContext) {
            $context = $twigContext['context']->getContext();
        }

        return $this->customFieldService->getCustomFieldLabels($customFieldNames, $context);
    }
}
