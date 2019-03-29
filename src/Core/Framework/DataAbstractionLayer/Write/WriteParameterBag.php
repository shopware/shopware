<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\FieldExceptionStack;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\Framework\Uuid\Uuid;

class WriteParameterBag
{
    /**
     * Defines the entity definition where the field placed in
     *
     * @var string
     */
    protected $definition;

    /**
     * Contains the write context instance of the current write process
     *
     * @var WriteContext
     */
    protected $context;

    /**
     * Contains the current property path for the proccessed field e.g product/{id}/name
     *
     * @var string
     */
    protected $path;

    /**
     * Contains all already applied write commands of the current write process
     *
     * @var WriteCommandQueue
     */
    protected $commandQueue;

    /**
     * The field exception stack contain all already thrown field exception of the current write process
     *
     * @var FieldExceptionStack
     */
    protected $exceptionStack;

    /**
     * @var string|null
     */
    private $currentWriteLanguageId;

    public function __construct(
        string $definition,
        WriteContext $context,
        string $path,
        WriteCommandQueue $commandQueue,
        FieldExceptionStack $exceptionStack
    ) {
        $this->definition = $definition;
        $this->context = $context;
        $this->path = $path;
        $this->commandQueue = $commandQueue;
        $this->exceptionStack = $exceptionStack;
    }

    /**
     * @return string|EntityDefinition
     */
    public function getDefinition(): string
    {
        return $this->definition;
    }

    public function getContext(): WriteContext
    {
        return $this->context;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getCommandQueue(): WriteCommandQueue
    {
        return $this->commandQueue;
    }

    public function getExceptionStack(): FieldExceptionStack
    {
        return $this->exceptionStack;
    }

    public function cloneForSubresource(string $definition, string $path): self
    {
        return new self(
            $definition,
            $this->context,
            $path,
            $this->commandQueue,
            $this->exceptionStack
        );
    }

    public function getCurrentWriteLanguageId(): string
    {
        if ($this->currentWriteLanguageId !== null) {
            return $this->currentWriteLanguageId;
        }

        return $this->context->getContext()->getLanguageId();
    }

    public function setCurrentWriteLanguageId(string $languageId): void
    {
        if (!Uuid::isValid($languageId)) {
            throw new LanguageNotFoundException($languageId);
        }

        $this->currentWriteLanguageId = $languageId;
    }
}
