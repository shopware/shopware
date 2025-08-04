<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\Log\Package;

#[Package('after-sales')]
/**
 * @deprecated tag:v6.8.0 - reason:remove-subscriber - Will be removed, as the exception handler is no longer needed, languages now also throw RestrictDeleteViolationException
 * @see RestrictDeleteViolationException is now thrown instead
 */
class NewsletterExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    public function matchException(\Throwable $e): ?\Exception
    {
        return null;
    }
}
