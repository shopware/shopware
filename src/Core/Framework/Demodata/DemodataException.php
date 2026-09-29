<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata;

use Shopware\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class DemodataException extends HttpException
{
    final public const WRONG_EXECUTION_ORDER = 'FRAMEWORK__WRONG_EXECUTION_ORDER';
    final public const NO_GENERATOR_FOUND = 'FRAMEWORK__NO_GENERATOR_FOUND';
    final public const INVALID_ARGUMENT = 'FRAMEWORK__DEMODATA_INVALID_ARGUMENT';

    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'self')]
    public static function invalidArgument(string $message): self|\InvalidArgumentException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return new \InvalidArgumentException($message);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_ARGUMENT,
            $message,
        );
    }

    public static function wrongExecutionOrder(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::WRONG_EXECUTION_ORDER,
            'This demo data command should be executed after the original demo data was executed at least one time',
        );
    }

    public static function noGeneratorFound(string $entity): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::NO_GENERATOR_FOUND,
            'Could not generate demodata for "{{ entity }}" because no generator is registered.',
            ['entity' => $entity]
        );
    }
}
