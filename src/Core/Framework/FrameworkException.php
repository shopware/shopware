<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Exception\InvalidOptionsException;
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * @codeCoverageIgnore
 */
#[Package('core')]
class FrameworkException extends HttpException
{
    private const PROJECT_DIR_NOT_EXISTS = 'FRAMEWORK__PROJECT_DIR_NOT_EXISTS';

    private const INVALID_KERNEL_CACHE_DIR = 'FRAMEWORK__INVALID_KERNEL_CACHE_DIR';

    private const INVALID_EVENT_DATA = 'FRAMEWORK__INVALID_EVENT_DATA';

    private const INVALID_ARGUMENT = 'FRAMEWORK__INVALID_ARGUMENT';

    private const INVALID_COLLECTION_ELEMENT_TYPE = 'FRAMEWORK__INVALID_COLLECTION_ELEMENT_TYPE';

    private const INVALID_COMPRESSION_METHOD = 'FRAMEWORK__INVALID_COMPRESSION_METHOD';
    private const EXTENSION_RESULT_NOT_SET = 'FRAMEWORK__EXTENSION_RESULT_NOT_SET';
    private const VALIDATION_FAILED = 'FRAMEWORK__VALIDATION_FAILED';
    private const CLASS_NOT_FOUND = 'FRAMEWORK__CLASS_NOT_FOUND';

    private const MISSING_OPTIONS = 'FRAMEWORK__MISSING_OPTIONS';
    private const INVALID_OPTIONS = 'FRAMEWORK__INVALID_OPTIONS';

    public static function projectDirNotExists(string $dir, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PROJECT_DIR_NOT_EXISTS,
            'Project directory "{{ dir }}" does not exist.',
            ['dir' => $dir],
            $e
        );
    }

    public static function invalidKernelCacheDir(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_KERNEL_CACHE_DIR,
            'Container parameter "kernel.cache_dir" needs to be a string.'
        );
    }

    public static function invalidEventData(string $message): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_EVENT_DATA,
            $message
        );
    }

    public static function invalidCompressionMethod(string $method): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_COMPRESSION_METHOD,
            \sprintf('Invalid cache compression method: %s', $method),
        );
    }

    public static function extensionResultNotSet(string $extension): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::EXTENSION_RESULT_NOT_SET,
            'Extension result not set for extension "{{ extension }}".',
            ['extension' => $extension]
        );
    }

    public static function invalidArgumentException(string $message): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_ARGUMENT,
            $message
        );
    }

    public static function validationFailed(string $message): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::VALIDATION_FAILED,
            $message
        );
    }

    public static function classNotFound(string $class): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CLASS_NOT_FOUND,
            'Class not found: ' . $class
        );
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return 'self' in the future
     */
    public static function collectionElementInvalidType(string $expectedClass, string $elementClass): self|\InvalidArgumentException
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new \InvalidArgumentException(
                \sprintf('Expected collection element of type %s got %s', $expectedClass, $elementClass)
            );
        }

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_COLLECTION_ELEMENT_TYPE,
            'Expected collection element of type {{ expected }} got {{ element }}',
            ['expected' => $expectedClass, 'element' => $elementClass]
        );
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return 'self' in the future
     * @deprecated tag:v6.7.0 - reason:argument-will-be-removed - $option will be removed
     *
     * @param array<string> $option
     */
    public static function missingOptions(string $message, array $option): self|MissingOptionsException
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new MissingOptionsException($message, $option);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_OPTIONS,
            $message
        );
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return 'self' in the future
     * @deprecated tag:v6.7.0 - reason:argument-will-be-removed - $option will be removed
     *
     * @param array<string> $option
     */
    public static function invalidOptions(string $message, array $option): self|InvalidOptionsException
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new InvalidOptionsException($message, $option);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_OPTIONS,
            $message
        );
    }
}
