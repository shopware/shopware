<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Error;

use Shopware\Core\Framework\Struct\AssignArrayTrait;
use Shopware\Core\Framework\Struct\CreateFromTrait;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Shopware\Core\Framework\Struct\Struct;

class ErrorMessage extends Struct
{
    /** @var string */
    protected $message;

    /** @var null|int */
    protected $line;

    /** @var null|int */
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
}
