<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException;

class MalformatDataException extends WriteFieldException
{
    private const CONCERN = 'data-malformat';
    /**
     * @var string
     */
    private $path;

    public function __construct(string $path, $message, $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->path = $path;
        $this->message = $message;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getConcern(): string
    {
        return self::CONCERN;
    }

    public function toArray(): array
    {
        return [$this->message];
    }
}
