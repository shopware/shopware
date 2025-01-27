<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * This route is used to get the generated document from a documentId
 */
#[Package('after-sales')]
abstract class AbstractDocumentRoute
{
    abstract public function getDecorated(): AbstractDocumentRoute;

    /**
     * @deprecated tag:v6.7.0 - Parameter $fileType will be added - reason:new-optional-parameter
     */
    abstract public function download(
        string $documentId,
        Request $request,
        SalesChannelContext $context,
        string $deepLinkCode = '',
        /* , string $fileType = PdfRenderer::FILE_EXTENSION */
    ): Response;
}
