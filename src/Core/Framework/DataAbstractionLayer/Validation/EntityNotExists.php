<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Validation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterRemoval;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Shopware\Core\Framework\Deprecation\BCChange\VisibilityChange;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

#[Package('framework')]
class EntityNotExists extends Constraint
{
    final public const ENTITY_EXISTS = 'fr456trg-r43w-ko87-z54e-de4r5tghzt65';

    protected const ERROR_NAMES = [
        self::ENTITY_EXISTS => 'ENTITY_EXISTS',
    ];

    #[VisibilityChange(version: 'v6.8.0', newVisibility: 'protected', description: 'Use getMessage() instead.')]
    public string $message = 'The {{ entity }} entity already exists.';

    protected string $entity;

    protected Context $context;

    protected Criteria $criteria;

    protected string $primaryProperty = 'id';

    /**
     * @param array{entity?: string, context?: Context, criteria?: Criteria, primaryProperty?: string}|null $options
     *
     * The `$entity`, `$context`, `$primaryProperty` and `$message` properties will be natively typed via constructor property promotion in v6.8.0.
     *
     * @internal
     */
    #[HasNamedArguments]
    #[ParameterRemoval(version: 'v6.8.0', parameterName: 'options', description: 'Use the named arguments instead.')]
    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'entity', newType: 'string', description: 'The parameter loses its null default and becomes required.')]
    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'context', newType: Context::class, description: 'The parameter loses its null default and becomes required.')]
    public function __construct(
        ?array $options = null,
        ?string $entity = null,
        ?Context $context = null,
        string $primaryProperty = 'id',
        ?Criteria $criteria = null,
        string $message = 'The {{ entity }} entity already exists.'
    ) {
        if ($options !== null || $entity === null || $context === null) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'Use $entity and $context arguments instead of providing it in $options array')
            );
        }

        if ($options === null || Feature::isActive('v6.8.0.0')) {
            if ($entity === null) {
                throw FrameworkException::missingOptions(\sprintf('Option "entity" must be given for constraint %s', self::class));
            }

            if ($context === null) {
                throw FrameworkException::missingOptions(\sprintf('Option "context" must be given for constraint %s', self::class));
            }

            parent::__construct();

            $this->entity = $entity;
            $this->context = $context;
            $this->criteria = $criteria ?? new Criteria();
            $this->primaryProperty = $primaryProperty;
            $this->message = $message;
        } else {
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

            if (!$options['criteria'] instanceof Criteria) {
                throw FrameworkException::invalidOptions(\sprintf('Option "criteria" must be an instance of Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria for constraint %s', self::class));
            }

            if (isset($options['primaryProperty']) && !\is_string($options['primaryProperty'])) {
                throw FrameworkException::invalidOptions(\sprintf('Option "primaryProperty" must be a string for constraint %s', self::class));
            }

            parent::__construct($options);
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

    public function getPrimaryProperty(): string
    {
        return $this->primaryProperty;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
