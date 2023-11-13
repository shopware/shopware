<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Exception;

use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('buyers-experience')]
class PatternNotComplexEnoughException extends PromotionException
{
    /**
     * @deprecated tag:v6.6.0 - will be removed, use PromotionException::PATTERN_NOT_COMPLEX_ENOUGH instead
     */
    final public const ERROR_CODE = self::PATTERN_NOT_COMPLEX_ENOUGH;

    /**
     * @deprecated tag:v6.6.0 - will be removed, use PromotionException::patternNotComplexEnough instead
     */
    public function __construct(
        protected int $statusCode = Response::HTTP_BAD_REQUEST,
        protected string $errorCode = self::PATTERN_NOT_COMPLEX_ENOUGH,
        string $message = 'The amount of possible codes is too low for the current pattern. Make sure your pattern is sufficiently complex.',
        array $parameters = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            $statusCode,
            $errorCode,
            $message,
            $parameters,
            $previous
        );
    }
}
