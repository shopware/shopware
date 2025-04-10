<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Validation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

#[Package('framework')]
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
     * @param array{entity: string, context: Context, criteria?: Criteria, primaryProperty?: string} $options
     *
     * @internal
     */
    public function __construct(array $options)
    {
        $options = array_merge(
            ['criteria' => new Criteria()],
            $options
        );

        if (!\is_string($options['entity'] ?? null)) {
            throw FrameworkException::missingOptions(\sprintf('Option "entity" must be given for constraint %s', self::class));
        }

        if (!($options['context'] ?? null) instanceof Context) {
            throw FrameworkException::missingOptions(\sprintf('Option "context" must be given for constraint %s', self::class));
        }

        if (!($options['criteria'] ?? null) instanceof Criteria) {
            throw FrameworkException::missingOptions(\sprintf('Option "criteria" must be an instance of Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria for constraint %s', self::class));
        }

        if (isset($options['primaryProperty']) && !\is_string($options['primaryProperty'])) {
            throw FrameworkException::invalidOptions(\sprintf('Option "primaryProperty" must be a string for constraint %s', self::class));
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
