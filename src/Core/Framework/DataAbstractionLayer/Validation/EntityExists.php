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
class EntityExists extends Constraint
{
    public const ENTITY_DOES_NOT_EXISTS = 'f1e5c873-5baf-4d5b-8ab7-e422bfce91f1';

    public $message = 'The {{ entity }} entity with id {{ id }} does not exists.';

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
        self::ENTITY_DOES_NOT_EXISTS => 'ENTITY_DOES_NOT_EXISTS',
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
