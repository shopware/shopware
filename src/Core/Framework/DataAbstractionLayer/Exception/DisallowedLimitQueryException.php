<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class DisallowedLimitQueryException extends ShopwareHttpException
{
    /**
     * @var array
     */
    private $allowedLimits;

    private $limit;

    public function __construct(array $allowedLimits, $limit)
    {
        $this->allowedLimits = $allowedLimits;
        $this->limit = $limit;

        parent::__construct(
            'The limit must be one of the `allowed_limits` [{{ allowedLimitsString }}]. Given: {{ limit }}',
            [
                'allowedLimitsString' => implode(', ', $allowedLimits),
                'allowedLimits' => $allowedLimits,
                'limit' => $limit,
            ]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__LIMIT_NOT_ALLOWED';
    }

    public function getAllowedLimits(): array
    {
        return $this->allowedLimits;
    }

    public function getLimit()
    {
        return $this->limit;
    }
}
