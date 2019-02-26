<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class IllegalTransitionException extends ShopwareHttpException
{
    protected $code = 'ILLEGAL-STATE-TRANSITION';

    public function __construct(string $currentState, string $transition, array $possibleTransitions, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf(
            'Illegal transition "%s" from state "%s". Possible transitions are: %s',
            $transition,
            $currentState,
            implode(', ', $possibleTransitions)
        );

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
