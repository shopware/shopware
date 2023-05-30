<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Traits;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class StateMachineMigration
{
    public function __construct(
        private string $technicalName,
        private string $de,
        private string $en,
        private array $states = [],
        private array $transitions = [],
        private ?string $initialState = null
    ) {
    }

    public static function state(string $technicalName, string $de, string $en): array
    {
        return ['technicalName' => $technicalName, 'de' => $de, 'en' => $en];
    }

    public static function transition(string $actionName, string $from, string $to): array
    {
        return ['actionName' => $actionName, 'from' => $from, 'to' => $to];
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getDe(): string
    {
        return $this->de;
    }

    public function setDe(string $de): void
    {
        $this->de = $de;
    }

    public function getEn(): string
    {
        return $this->en;
    }

    public function setEn(string $en): void
    {
        $this->en = $en;
    }

    public function getStates(): array
    {
        return $this->states;
    }

    public function setStates(array $states): void
    {
        $this->states = $states;
    }

    public function getTransitions(): array
    {
        return $this->transitions;
    }

    public function setTransitions(array $transitions): void
    {
        $this->transitions = $transitions;
    }

    public function getInitialState(): ?string
    {
        return $this->initialState;
    }

    public function setInitialState(?string $initialState): void
    {
        $this->initialState = $initialState;
    }
}
