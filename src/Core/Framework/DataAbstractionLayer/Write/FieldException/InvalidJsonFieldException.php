<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException;

use Throwable;

class InvalidJsonFieldException extends WriteFieldException
{
    private const CONCERN = 'validation-error';

    /**
     * @var InvalidFieldException[]
     */
    private $exceptions;

    /**
     * @var string
     */
    private $path;

    public function __construct(string $path, array $exceptions, $code = 0, Throwable $previous = null)
    {
        parent::__construct(
            sprintf('Caught %s validation errors.', \count($exceptions)),
            $code,
            $previous
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
        return self::CONCERN;
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
