<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Validation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\InvalidOptionsException;
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
class EntityNotExists extends Constraint
{
    public const ENTITY_EXISTS = 'fr456trg-r43w-ko87-z54e-de4r5tghzt65';

    public $message = 'The {{ entity }} entity already exists.';

    /**
     * @var string
     */
    public $entity;

    /**
     * @var Context
     */
    public $context;

    /**
     * @var Criteria
     */
    public $criteria;

    protected static $errorNames = [
        self::ENTITY_EXISTS => 'ENTITY_EXISTS',
    ];

    public function __construct(array $options)
    {
        $options = array_merge(
            ['entity' => null, 'context' => null, 'criteria' => new Criteria()],
            $options
        );

        parent::__construct($options);

        if ($this->entity === null) {
            throw new MissingOptionsException(sprintf('Option "entity" must be given for constraint %s', self::class), ['entity']);
        }

        if ($this->context === null) {
            throw new MissingOptionsException(sprintf('Option "context" must be given for constraint %s', self::class), ['context']);
        }

        if (!($this->criteria instanceof Criteria)) {
            throw new InvalidOptionsException(sprintf('Option "criteria" must be an instance of Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria for constraint %s', self::class), ['criteria']);
        }
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }
}
