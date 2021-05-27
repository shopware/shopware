<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow;

/**
 * @internal (FEATURE_NEXT_8225)
 */
class Sequence
{
    public ?string $ruleId = null;

    public ?string $action = null;

    public array $config = [];

    public ?Sequence $falseCase = null;

    public ?Sequence $trueCase = null;

    public ?Sequence $nextAction = null;

    public function isIf(): bool
    {
        return $this->ruleId !== null;
    }

    public function isAction(): bool
    {
        return $this->action !== null;
    }

    public static function createIF(string $ruleId, ?Sequence $true, ?Sequence $false): self
    {
        $sequence = new Sequence();
        $sequence->ruleId = $ruleId;
        $sequence->trueCase = $true;
        $sequence->falseCase = $false;

        return $sequence;
    }

    public static function createAction(string $action, ?Sequence $nextAction, array $config = []): self
    {
        $sequence = new Sequence();
        $sequence->action = $action;
        $sequence->config = $config;
        $sequence->nextAction = $nextAction;

        return $sequence;
    }
}
