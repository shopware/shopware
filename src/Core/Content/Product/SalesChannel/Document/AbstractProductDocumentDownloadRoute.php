<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Document;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Package('inventory')]
abstract class AbstractProductDocumentDownloadRoute
{
    abstract public function getDecorated(): AbstractProductDocumentDownloadRoute;

    abstract public function load(string $productId, string $documentId, Request $request, SalesChannelContext $context): Response;
}
