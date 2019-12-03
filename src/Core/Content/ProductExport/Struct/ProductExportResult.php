<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Struct;

use Shopware\Core\Content\ProductExport\Error\Error;

class ProductExportResult
{
    /** @var string */
    private $content;

    /** @var Error[] */
    private $errors;

    /** @var int */
    private $total;

    public function __construct(string $content, array $errors, int $total)
    {
        $this->content = $content;
        $this->errors = $errors;
        $this->total = $total;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function getTotal(): int
    {
        return $this->total;
    }
}
