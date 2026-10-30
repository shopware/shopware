<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.8.0 - Will be removed, use UtilException::operatorNotSupported()
 */
#[Package('framework')]
class ComparatorException extends HttpException
{
    public const OPERATOR_NOT_SUPPORTED = 'CONTENT__OPERATOR_NOT_SUPPORTED';

    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(int $statusCode, string $errorCode, string $message, array $parameters = [], ?\Throwable $previous = null)
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        parent::__construct($statusCode, $errorCode, $message, $parameters, $previous);
    }

    public static function operatorNotSupported(string $operator): self
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0'));

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::OPERATOR_NOT_SUPPORTED,
            'Operator "{{ operator }}" is not supported.',
            ['operator' => $operator]
        );
    }
}
