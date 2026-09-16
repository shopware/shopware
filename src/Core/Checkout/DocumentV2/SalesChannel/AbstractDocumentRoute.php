<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\SalesChannel;

use Shopware\Core\Framework\Deprecation\BCChange\ClassMoved;
use Shopware\Core\Framework\Deprecation\BCChange\NewOptionalParameter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * This route is used to get the generated document from a documentId
 */
#[Package('after-sales')]
#[ClassMoved(version: 'v6.9.0', previousClassName: 'Shopware\Core\Checkout\Document\SalesChannel\AbstractDocumentRoute')]
abstract class AbstractDocumentRoute
{
    /**
     * Mirrors DocumentFormat::PDF; a parameter default must be a constant expression, so the enum cannot be called here.
     */
    private const FILE_EXTENSION = 'pdf';

    abstract public function getDecorated(): AbstractDocumentRoute;

    #[NewOptionalParameter(version: 'v6.9.0', parameterName: 'format', parameterType: '?string', defaultValue: null, description: 'Selects which document v2 file to download by its associated format.')]
    abstract public function download(
        string $documentId,
        Request $request,
        SalesChannelContext $context,
        string $deepLinkCode = '',
        string $fileType = self::FILE_EXTENSION,
        /* , ?string $format = null */
    ): Response;
}
