<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\SalesChannel;

use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Framework\Deprecation\BCChange\NewOptionalParameter;
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

    #[NewOptionalParameter(version: 'v6.9.0', parameterName: 'format', parameterType: '?string', defaultValue: null, description: 'Selects which document v2 file to download by its associated format.')]
    abstract public function download(
        string $documentId,
        Request $request,
        SalesChannelContext $context,
        string $deepLinkCode = '',
        string $fileType = PdfRenderer::FILE_EXTENSION,
        /* , ?string $format = null */
    ): Response;
}
