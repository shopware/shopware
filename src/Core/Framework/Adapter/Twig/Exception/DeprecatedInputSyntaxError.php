<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Exception;

use Shopware\Core\Framework\Log\Package;
use Twig\Error\SyntaxError;
use Twig\Source;

/**
 * @internal
 */
#[Package('framework')]
final class DeprecatedInputSyntaxError extends SyntaxError
{
    public static function invalid(string $message, int $line, ?Source $source): self
    {
        return new self($message, $line, $source);
    }

    public static function raise(string $message, int $line, ?Source $source): never
    {
        throw self::invalid($message, $line, $source);
    }
}
