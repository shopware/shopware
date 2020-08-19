<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

class CustomFieldTypeNotFoundException extends \InvalidArgumentException
{
    public function __construct(string $type, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('CustomFieldType for XML-Element "%s" not found.', $type), $code, $previous);
    }
}
