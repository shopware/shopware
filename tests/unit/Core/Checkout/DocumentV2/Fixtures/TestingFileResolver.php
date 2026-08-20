<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures;

use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\Service\DocumentFileResolver\AbstractFileResolver;
use Shopware\Core\Checkout\DocumentV2\Struct\ResolvedDocumentFile;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final class TestingFileResolver extends AbstractFileResolver
{
    public function __construct(private readonly MediaEntity $media)
    {
    }

    public function resolve(DocumentEntity $document, string $format): ?ResolvedDocumentFile
    {
        return $this->createResolvedFile(
            $document,
            $this->media,
            $format,
            ResolvedDocumentFile::SOURCE_V2,
        );
    }
}
