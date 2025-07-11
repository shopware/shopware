<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class DomainVerificationRequestStruct extends Struct
{
    /**
     * @deprecated tag:v6.8.0 - reason:parameter-name-change - Parameter `filename` will be renamed to `fileName`
     */
    public function __construct(
        protected string $content,
        protected string $filename,
    ) {
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getFileName(): string
    {
        return $this->filename;
    }

    public function getApiAlias(): string
    {
        return 'store_domain_verification_request';
    }
}
