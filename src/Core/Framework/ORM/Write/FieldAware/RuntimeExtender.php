<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Write\FieldAware;

use Shopware\Core\Framework\ORM\Field\Field;
use Shopware\Core\Framework\ORM\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\ORM\Write\FieldException\FieldExceptionStack;
use Shopware\Core\Framework\ORM\Write\WriteCommandExtractor;
use Shopware\Core\Framework\ORM\Write\WriteContext;

class RuntimeExtender extends FieldExtender
{
    /**
     * @var WriteContext
     */
    private $writeContext;

    /**
     * @var WriteCommandQueue
     */
    private $commandQueue;

    /**
     * @var FieldExceptionStack
     */
    private $exceptionStack;

    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $definition;
    /**
     * @var WriteCommandExtractor
     */
    private $writeResource;

    public function __construct(
        string $definition,
        WriteContext $writeContext,
        WriteCommandQueue $commandQueue,
        FieldExceptionStack $exceptionStack,
        string $path,
        WriteCommandExtractor $writeResource
    ) {
        $this->writeContext = $writeContext;
        $this->commandQueue = $commandQueue;
        $this->exceptionStack = $exceptionStack;
        $this->path = $path;
        $this->definition = $definition;
        $this->writeResource = $writeResource;
    }

    public function extend(Field $field): void
    {
        $field->setDefinition($this->definition);
        $field->setWriteResource($this->writeResource);
        $field->setWriteContext($this->writeContext);
        $field->setCommandQueue($this->commandQueue);
        $field->setExceptionStack($this->exceptionStack);
        $field->setPath($this->path);
    }
}
