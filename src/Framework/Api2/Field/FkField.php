<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\Field;

use Shopware\Framework\Api2\FieldAware\WriteContextAware;
use Shopware\Framework\Api2\WriteContext;

class FkField extends Field implements WriteContextAware
{
    /**
     * @var string
     */
    private $storageName;

    /**
     * @var WriteContext
     */
    private $writeContext;

    /**
     * @var string
     */
    private $foreignClassName;

    /**
     * @var string
     */
    private $foreignFieldName;

    /**
     * @param string $storageName
     * @param string $foreignClassName
     * @param string $foreignFieldName
     */
    public function __construct(string $storageName, string $foreignClassName, string $foreignFieldName)
    {
        $this->foreignClassName = $foreignClassName;
        $this->foreignFieldName = $foreignFieldName;
        $this->storageName = $storageName;
    }

    /**
     * @return string
     */
    public function getStorageName(): string
    {
        return $this->storageName;
    }

    /**
     * @param WriteContext $writeContext
     */
    public function setWriteContext(WriteContext $writeContext): void
    {
        $this->writeContext = $writeContext;
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(string $type, string $key, $value = null): \Generator
    {
        if (!$value) {
            $value = $this->writeContext->get($this->foreignClassName, $this->foreignFieldName);
        }

        yield $this->storageName => $value;
    }
}
