<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Validation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

#[Package('framework')]
class EntityExists extends Constraint
{
    final public const ENTITY_DOES_NOT_EXISTS = 'f1e5c873-5baf-4d5b-8ab7-e422bfce91f1';

    protected const ERROR_NAMES = [
        self::ENTITY_DOES_NOT_EXISTS => 'ENTITY_DOES_NOT_EXISTS',
    ];

    /**
     * @deprecated tag:v6.8.0 - $message property access modifier will be changed to protected and is injectable via constructor
     */
    public string $message = 'The {{ entity }} entity with {{ primaryProperty }} {{ id }} does not exist.';

    protected string $entity;

    protected Context $context;

    protected Criteria $criteria;

    protected string $primaryProperty = 'id';

    /**
     * @param array{entity: string, context: Context, criteria?: Criteria, primaryProperty?: string}|null $options
     *
     * @deprecated tag:v6.8.0 - reason:new-optional-parameter - $options parameter will be removed
     * @deprecated tag:v6.8.0 - reason:new-optional-parameter - $entity and $context parameter will be required
     * @deprecated tag:v6.8.0 - reason:new-optional-parameter - $entity, $context, $primaryProperty and $message properties will be natively typed as constructor property promotion
     *
     * @internal
     */
    #[HasNamedArguments]
    public function __construct(
        ?array $options = null,
        ?string $entity = null,
        ?Context $context = null,
        string $primaryProperty = 'id',
        ?Criteria $criteria = null,
        string $message = 'The {{ entity }} entity with {{ primaryProperty }} {{ id }} does not exist.'
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

            if (!($options['criteria'] ?? null) instanceof Criteria) {
                throw FrameworkException::missingOptions(\sprintf('Option "criteria" must be an instance of Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria for constraint %s', self::class));
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
