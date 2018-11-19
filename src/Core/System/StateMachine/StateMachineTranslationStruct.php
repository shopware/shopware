<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\System\Language\LanguageStruct;

class StateMachineTranslationStruct extends Entity
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $stateMachineId;

    /**
     * @var StateMachineStruct
     */
    protected $stateMachine;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var LanguageStruct
     */
    protected $language;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getStateMachineId(): string
    {
        return $this->stateMachineId;
    }

    public function setStateMachineId(string $stateMachineId): void
    {
        $this->stateMachineId = $stateMachineId;
    }

    public function getStateMachine(): StateMachineStruct
    {
        return $this->stateMachine;
    }

    public function setStateMachine(StateMachineStruct $stateMachine): void
    {
        $this->stateMachine = $stateMachine;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getLanguage(): LanguageStruct
    {
        return $this->language;
    }

    public function setLanguage(LanguageStruct $language): void
    {
        $this->language = $language;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
