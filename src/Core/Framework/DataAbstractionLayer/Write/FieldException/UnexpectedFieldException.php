<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException;

class UnexpectedFieldException extends WriteFieldException
{
    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $fieldName;

    public function __construct(string $path, string $fieldName)
    {
        parent::__construct(
            'Unexpected field: {{ field }}',
            ['field' => $fieldName]
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
        return 'unexpected-field';
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
