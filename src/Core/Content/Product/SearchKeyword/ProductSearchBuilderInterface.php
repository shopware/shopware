<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SearchKeyword;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

interface ProductSearchBuilderInterface
{
    public function build(Request $request, Criteria $criteria, SalesChannelContext $context): void;
}
