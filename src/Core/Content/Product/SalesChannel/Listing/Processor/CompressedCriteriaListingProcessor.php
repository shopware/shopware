<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing\Processor;

use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This processor adds support of ProductListingCriteria fields passed in the compressed criteria payload.
 * It should run before any other filter/processor that relies on request parameters.
 *
 * @internal
 */
#[Package('inventory')]
class CompressedCriteriaListingProcessor extends AbstractListingProcessor
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CompressedCriteriaDecoder $compressedCriteriaDecoder,
    ) {
    }

    public function getDecorated(): AbstractListingProcessor
    {
        throw new DecorationPatternException(self::class);
    }

    public function prepare(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        if (!$request->isMethod(Request::METHOD_GET)) {
            return;
        }

        if (!$request->query->has('_criteria')) {
            return;
        }

        $payload = $this->compressedCriteriaDecoder->decode((string) $request->query->get('_criteria'));
        foreach ($payload as $param => $value) {
            if (!\in_array($param, RequestCriteriaBuilder::KNOWN_FIELDS, true)) {
                // adding compressed criteria fields to the request query parameters simulating normal request parameters
                // mutating request is not ideal, but this way existing plugins with custom filters will continue to work without changes
                $request->query->set($param, $value);
            }
        }
    }

    public function process(Request $request, ProductListingResult $result, SalesChannelContext $context): void
    {
    }
}
