<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class DomainVerificationRequestStruct extends Struct
{
    /**
     * @deprecated tag:v6.8.0 - Property will be removed, and currently is only there for old serialized objects, use `fileName` instead
     */
    protected string $filename;

    /**
     * @deprecated tag:v6.8.0 - reason:parameter-name-change - Parameter `filename` will be renamed to `fileName`
     */
    public function __construct(
        protected string $content,
        protected string $fileName,
    ) {
        if (!Feature::isActive('v6.8.0.0')) {
            $this->filename = $this->fileName;
        }
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getFileName(): string
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return $this->fileName ?? $this->filename;
        }

        return $this->fileName;
    }

    public function getApiAlias(): string
    {
        return 'store_domain_verification_request';
    }
}
