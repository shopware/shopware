<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class IllegalTransitionException extends ShopwareHttpException
{
    public function __construct(
        string $currentState,
        string $transition,
        array $possibleTransitions
    ) {
        parent::__construct(
            'Illegal transition "{{ transition }}" from state "{{ currentState }}". Possible transitions are: {{ possibleTransitionsString }}',
            [
                'transition' => $transition,
                'currentState' => $currentState,
                'possibleTransitionsString' => implode(', ', $possibleTransitions),
                'possibleTransitions' => $possibleTransitions,
            ]
        );
    }

    public function getErrorCode(): string
    {
        return 'SYSTEM__ILLEGAL_STATE_TRANSITION';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
