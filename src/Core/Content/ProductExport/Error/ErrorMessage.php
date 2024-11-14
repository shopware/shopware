<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Error;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('inventory')]
class ErrorMessage extends Struct
{
    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $message;

    /**
     * @var int|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $line;

    /**
     * @var int|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $column;

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getLine(): ?int
    {
        return $this->line;
    }

    public function getColumn(): ?int
    {
        return $this->column;
    }

    public function getApiAlias(): string
    {
        return 'product_export_error_message';
    }
}
