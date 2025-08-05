<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
/**
 * @deprecated tag:v6.8.0 - reason:remove-subscriber - Will be removed, as the exception handler is no longer needed, languages now also throw RestrictDeleteViolationException
 * @see RestrictDeleteViolationException is now thrown instead
 */
class ThemeExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    public function matchException(\Throwable $e): ?\Throwable
    {
        return null;
    }
}
