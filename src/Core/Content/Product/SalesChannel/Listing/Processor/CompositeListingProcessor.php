<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing\Processor;

use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('inventory')]
final class CompositeListingProcessor
{
    /**
     * @param iterable<AbstractListingProcessor> $processors
     *
     * @internal
     */
    public function __construct(private readonly iterable $processors)
    {
    }

    public function getDecorated(): AbstractListingProcessor
    {
        throw new DecorationPatternException(self::class);
    }

    public function prepare(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        foreach ($this->processors as $processor) {
            $processor->prepare($request, $criteria, $context);
        }
    }

    public function process(Request $request, ProductListingResult $result, SalesChannelContext $context): void
    {
        foreach ($this->processors as $processor) {
            $processor->process($request, $result, $context);
        }
    }
}
