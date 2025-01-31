<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Traits;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @phpstan-type State array{id?: string, technicalName: string}
 * @phpstan-type Transition array{id?: string, actionName: string, fromStateId?: string, from?: string, toStateId?: string, to?: string}
 */
#[Package('framework')]
readonly class StateMachineMigration
{
    /**
     * @param list<State> $states
     * @param list<Transition> $transitions
     */
    public function __construct(
        private string $technicalName,
        private string $de,
        private string $en,
        private array $states = [],
        private array $transitions = [],
        private ?string $initialState = null
    ) {
    }

    /**
     * @return array{technicalName: string, de: string, en: string}
     */
    public static function state(string $technicalName, string $de, string $en): array
    {
        return ['technicalName' => $technicalName, 'de' => $de, 'en' => $en];
    }

    /**
     * @return array{actionName: string, from: string, to: string}
     */
    public static function transition(string $actionName, string $from, string $to): array
    {
        return ['actionName' => $actionName, 'from' => $from, 'to' => $to];
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function getDe(): string
    {
        return $this->de;
    }

    public function getEn(): string
    {
        return $this->en;
    }

    /**
     * @return list<State>
     */
    public function getStates(): array
    {
        return $this->states;
    }

    /**
     * @return list<Transition>
     */
    public function getTransitions(): array
    {
        return $this->transitions;
    }

    public function getInitialState(): ?string
    {
        return $this->initialState;
    }
}
