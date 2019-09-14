<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Struct;

use Shopware\Core\Content\ProductExport\Error\Error;

class ProductExportResult
{
    /** @var string */
    private $content;

    /** @var Error[] */
    private $errors;

    public function __construct(string $content, array $errors)
    {
        $this->content = $content;
        $this->errors  = $errors;
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
}
