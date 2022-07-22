<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\SalesChannel;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * This route is used to get the generated document from a documentId
 */
abstract class AbstractDocumentRoute
{
    abstract public function getDecorated(): AbstractDocumentRoute;

    abstract public function download(string $documentId, Request $request, SalesChannelContext $context, string $deepLinkCode = ''): Response;
}
