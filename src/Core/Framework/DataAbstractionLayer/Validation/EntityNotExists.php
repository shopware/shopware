<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Validation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

#[Package('core')]
class EntityNotExists extends Constraint
{
    final public const ENTITY_EXISTS = 'fr456trg-r43w-ko87-z54e-de4r5tghzt65';

    protected const ERROR_NAMES = [
        self::ENTITY_EXISTS => 'ENTITY_EXISTS',
    ];

    public string $message = 'The {{ entity }} entity already exists.';

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
            throw FrameworkException::missingOptions(\sprintf('Option "entity" must be given for constraint %s', self::class), ['entity']);
        }

        if (!($options['context'] ?? null) instanceof Context) {
            throw FrameworkException::missingOptions(\sprintf('Option "context" must be given for constraint %s', self::class), ['context']);
        }

        if (!($options['criteria'] ?? null) instanceof Criteria) {
            throw FrameworkException::invalidOptions(\sprintf('Option "criteria" must be an instance of Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria for constraint %s', self::class), ['criteria']);
        }

        if (isset($options['primaryProperty']) && !\is_string($options['primaryProperty'])) {
            throw FrameworkException::invalidOptions(\sprintf('Option "primaryProperty" must be a string for constraint %s', self::class), ['primaryProperty']);
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
