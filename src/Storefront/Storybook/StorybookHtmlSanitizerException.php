<?php declare(strict_types=1);

namespace Shopware\Storefront\Storybook;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class StorybookHtmlSanitizerException extends \RuntimeException
{
    public static function unableToSanitize(?\Throwable $previous = null): self
    {
        return new self('Storybook HTML could not be sanitized.', 0, $previous);
    }
}
