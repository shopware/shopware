<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException;

class InvalidJsonFieldException extends WriteFieldException
{
    /**
     * @var InvalidFieldException[]
     */
    private $exceptions;

    /**
     * @var string
     */
    private $path;

    public function __construct(string $path, array $exceptions)
    {
        parent::__construct(
            'Caught {{ count }} validation errors.',
            ['count' => count($exceptions)]
        );

        $this->path = $path;
        $this->exceptions = $exceptions;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getConcern(): string
    {
        return 'validation-error';
    }

    public function getExceptions(): array
    {
        return $this->exceptions;
    }

    public function toArray(): array
    {
        exit('FOO');
    }
}
