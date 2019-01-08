<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\DataAbstractionLayer\Indexing;

class ConditionClassNotFound extends \RuntimeException
{
    /**
     * @var string
     */
    private $className;

    public function __construct(string $className)
    {
        parent::__construct();
        $this->className = $className;
    }

    public function getClassName(): string
    {
        return $this->className;
    }
}
