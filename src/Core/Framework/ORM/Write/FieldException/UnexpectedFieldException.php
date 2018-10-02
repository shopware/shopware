<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Write\FieldException;

use Throwable;

class UnexpectedFieldException extends WriteFieldException
{
    private const CONCERN = 'unexpected-field';

    /**
     * @var string
     */
    private $path;
    /**
     * @var string
     */
    private $fieldName;

    public function __construct(string $path, string $fieldName, $code = 0, Throwable $previous = null)
    {
        parent::__construct(
            sprintf('Unexpected field: %s', $fieldName),
            $code,
            $previous
        );
        $this->path = $path;
        $this->fieldName = $fieldName;
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
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
        return [
            'message' => $this->getMessage(),
            'messageTemplate' => $this->getMessage(),
            'parameters' => [],
            'propertyPath' => $this->getPath(),
        ];
    }
}
