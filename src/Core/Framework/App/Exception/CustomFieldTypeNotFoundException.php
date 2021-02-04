<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class CustomFieldTypeNotFoundException extends \InvalidArgumentException
{
    public function __construct(string $type, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('CustomFieldType for XML-Element "%s" not found.', $type), $code, $previous);
    }
}
