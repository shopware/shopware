<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Field;

use Shopware\Api\Write\Flag\Deferred;
use Shopware\Api\Write\Flag\ReadOnly;

class StructField extends Field implements AssociationInterface
{
    /**
     * @var bool
     */
    private $inBasic;

    /**
     * @var string
     */
    private $structClass;

    public function __construct(string $propertyName, string $structClass, bool $inBasic)
    {
        parent::__construct($propertyName);
        $this->inBasic = $inBasic;
        $this->structClass = $structClass;
        $this->setFlags(new ReadOnly());
        $this->setFlags(new Deferred());
    }

    public function __invoke(string $type, string $key, $value = null): \Generator
    {
        throw new \Exception('Struct fields can be invoked in write context');
    }

    public function loadInBasic(): bool
    {
        return $this->inBasic;
    }

    public function getReferenceClass(): string
    {
        return $this->structClass;
    }
}
