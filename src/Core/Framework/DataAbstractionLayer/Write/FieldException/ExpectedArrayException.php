<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException;

class ExpectedArrayException extends WriteFieldException
{
    /**
     * @var string
     */
    private $path;

    public function __construct(string $path)
    {
        parent::__construct('Expected data to be array.');

        $this->path = $path;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getConcern(): string
    {
        return 'data-malformat';
    }

    public function toArray(): array
    {
        return [$this->getMessage()];
    }
}
