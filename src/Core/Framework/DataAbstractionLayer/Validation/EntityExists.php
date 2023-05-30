<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Validation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\InvalidOptionsException;
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * @Annotation
 *
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
#[Package('core')]
class EntityExists extends Constraint
{
    final public const ENTITY_DOES_NOT_EXISTS = 'f1e5c873-5baf-4d5b-8ab7-e422bfce91f1';

    protected const ERROR_NAMES = [
        self::ENTITY_DOES_NOT_EXISTS => 'ENTITY_DOES_NOT_EXISTS',
    ];

    public string $message = 'The {{ entity }} entity with {{ primaryProperty }} {{ id }} does not exist.';

    protected string $entity;

    protected Context $context;

    protected Criteria $criteria;

    protected string $primaryProperty = 'id';

    /**
     * @internal
     *
     * @param array<string, mixed> $options
     */
    public function __construct(array $options)
    {
        $options = array_merge(
            ['criteria' => new Criteria()],
            $options
        );

        if (!\is_string($options['entity'] ?? null)) {
            throw new MissingOptionsException(sprintf('Option "entity" must be given for constraint %s', self::class), ['entity']);
        }

        if (!($options['context'] ?? null) instanceof Context) {
            throw new MissingOptionsException(sprintf('Option "context" must be given for constraint %s', self::class), ['context']);
        }

        if (!($options['criteria'] ?? null) instanceof Criteria) {
            throw new InvalidOptionsException(sprintf('Option "criteria" must be an instance of Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria for constraint %s', self::class), ['criteria']);
        }

        parent::__construct($options);
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

    public function getPrimaryProperty(): string
    {
        return $this->primaryProperty;
    }
}
