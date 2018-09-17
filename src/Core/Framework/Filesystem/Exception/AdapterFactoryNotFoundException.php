<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Filesystem\Exception;

class AdapterFactoryNotFoundException extends \Exception
{
    public static function fromAdapterType(string $type): self
    {
        return new self(sprintf('Adapter factory for type "%s" was not found.', $type));
    }
}
