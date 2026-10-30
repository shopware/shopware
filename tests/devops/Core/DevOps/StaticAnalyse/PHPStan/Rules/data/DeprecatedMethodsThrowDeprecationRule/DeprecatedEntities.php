<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\MyFakeNamespace;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Feature;

/**
 * @deprecated tag:v6.8.0 - Will be removed.
 */
class RemovedDefinition extends EntityDefinition
{
    public function __construct()
    {
        Feature::throwIfActive('v6.8.0.0', 'removed');
    }

    public function getEntityName(): string
    {
        return 'removed';
    }

    public function extraPublicMethod(): void
    {
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection();
    }
}

/**
 * @deprecated tag:v6.8.0 - Will be removed.
 */
class UnguardedDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'unguarded';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection();
    }
}

/**
 * @deprecated tag:v6.8.0 - Will be removed.
 *
 * @extends EntityCollection<Entity>
 */
class RemovedCollection extends EntityCollection
{
    public function __construct(iterable $elements = [])
    {
        Feature::throwIfActive('v6.8.0.0', 'removed');
        parent::__construct($elements);
    }

    public function getApiAlias(): string
    {
        return 'removed_collection';
    }

    protected function getExpectedClass(): string
    {
        return Entity::class;
    }
}

/**
 * @deprecated tag:v6.8.0 - Will be removed.
 *
 * @extends EntityCollection<Entity>
 */
class WrongFlagCollection extends EntityCollection
{
    public function __construct(iterable $elements = [])
    {
        Feature::throwIfActive('v6.9.0.0', 'wrong flag');
        parent::__construct($elements);
    }

    protected function getExpectedClass(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', 'unsafe metadata');

        return Entity::class;
    }
}

/**
 * @deprecated tag:v6.8.0 - Will be removed.
 */
class RemovedEntity extends Entity
{
    public function getLabel(): string
    {
        return 'unguarded';
    }
}

/**
 * @deprecated tag:v6.9.0 - Will be removed.
 *
 * @extends EntityCollection<Entity>
 */
class UnguardedFutureCollection extends EntityCollection
{
}

/**
 * @deprecated tag:v6.8.0 - Will be removed.
 *
 * @extends EntityCollection<Entity>
 */
class LateGuardCollection extends EntityCollection
{
    public function __construct(iterable $elements = [])
    {
        $elements = [...$elements];
        Feature::throwIfActive('v6.8.0.0', 'too late');
        parent::__construct($elements);
    }
}

/**
 * @deprecated tag:v6.8.0 - Will be removed.
 */
class GuardedEntity extends Entity
{
    public function __construct()
    {
        Feature::throwIfActive('v6.8.0.0', 'removed');
    }

    public function getLabel(): string
    {
        return 'safe';
    }

    /**
     * @deprecated tag:v6.8.0 - Use getLabel instead.
     */
    public function getLegacyLabel(): string
    {
        return 'unguarded explicit deprecation';
    }
}
