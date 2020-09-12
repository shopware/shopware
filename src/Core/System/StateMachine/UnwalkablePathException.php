<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class UnwalkablePathException extends ShopwareHttpException
{
    /**
     * @var string
     */
    private $entityName;

    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $fromState;

    /**
     * @var string
     */
    private $toState;

    public function __construct(
        string $entityName,
        string $id,
        string $fromState,
        string $toState,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            sprintf('Unabled to walk from state %s to state %s for %s(%s)', $fromState, $toState, $entityName, $id),
            [
                'entityName' => $entityName,
                'id' => $id,
                'fromState' => $fromState,
                'toState' => $toState,
            ],
            $previous
        );
        $this->entityName = $entityName;
        $this->id = $id;
        $this->fromState = $fromState;
        $this->toState = $toState;
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getFromState(): string
    {
        return $this->fromState;
    }

    public function getToState(): string
    {
        return $this->toState;
    }

    public function getErrorCode(): string
    {
        return (string) Response::HTTP_EXPECTATION_FAILED;
    }
}
