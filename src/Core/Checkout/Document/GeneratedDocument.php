<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @deprecated tag:v6.5.0 - Will be removed, use RenderedDocument instead
 */
class GeneratedDocument extends Struct
{
    /**
     * @var string
     */
    protected $filename;

    /**
     * @var string
     */
    protected $fileBlob;

    /**
     * @var string
     */
    protected $pageOrientation = 'portrait';

    /**
     * @var string
     */
    protected $pageSize = 'a4';

    /**
     * @var string
     */
    protected $html;

    /**
     * @var string
     */
    protected $contentType;

    public function getFilename(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return $this->filename;
    }

    public function setFilename(string $filename): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        $this->filename = $filename;
    }

    public function getFileBlob(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return $this->fileBlob;
    }

    public function setFileBlob(string $fileBlob): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        $this->fileBlob = $fileBlob;
    }

    public function getPageOrientation(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return $this->pageOrientation;
    }

    public function setPageOrientation(?string $pageOrientation): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        if ($pageOrientation !== null) {
            $this->pageOrientation = $pageOrientation;
        }
    }

    public function getHtml(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return $this->html;
    }

    public function setHtml(string $html): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        $this->html = $html;
    }

    public function getPageSize(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return $this->pageSize;
    }

    /**
     * @param string $pageSize
     */
    public function setPageSize(?string $pageSize): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        if ($pageSize !== null) {
            $this->pageSize = $pageSize;
        }
    }

    public function getContentType(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return $this->contentType;
    }

    public function setContentType(string $contentType): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        $this->contentType = $contentType;
    }

    public function getApiAlias(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return 'document_generated';
    }
}
