<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Validation;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\Event\BeforeSystemConfigChangedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The default-layout gate, the system-config counterpart of the entity-assignment gate: a default layout config value
 * must name an existing layout whose immutable root source matches the key's entity type, and a layout that is a
 * default in any sales channel cannot be deleted. A null value unsets the default. The guarded keys are the ones the
 * assignment definitions declare through `getContentLayoutDefaultConfigKey()`, the same hook resolution reads.
 *
 * @internal
 */
#[Package('framework')]
class ContentLayoutDefaultValidator implements EventSubscriberInterface
{
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly LayoutRootSourceReader $rootSourceReader,
        private readonly Connection $connection,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeSystemConfigChangedEvent::class => 'validateDefaultChange',
            EntityDeleteEvent::class => 'validateDeletion',
        ];
    }

    public function validateDefaultChange(BeforeSystemConfigChangedEvent $event): void
    {
        $expected = $this->defaultLayoutConfigKeys()[$event->getKey()] ?? null;
        $layoutId = $event->getValue();

        if ($expected === null || $layoutId === null || $layoutId === '') {
            return;
        }

        if (!\is_string($layoutId)) {
            throw ContentSystemException::contentLayoutNotFound((string) json_encode($layoutId));
        }

        $rootSource = $this->rootSourceReader->read($layoutId, [], Context::createDefaultContext());

        if ($rootSource === null) {
            throw ContentSystemException::contentLayoutNotFound($layoutId);
        }

        if ($rootSource !== $expected) {
            throw ContentSystemException::rootSourceAssignmentMismatch($rootSource, $expected);
        }
    }

    public function validateDeletion(EntityDeleteEvent $event): void
    {
        $layoutIds = array_filter($event->getIds(ContentLayoutDefinition::ENTITY_NAME), '\is_string');

        if ($layoutIds === []) {
            return;
        }

        $defaultLayoutIds = array_values(array_intersect($layoutIds, $this->fetchDefaultLayoutIds()));

        if ($defaultLayoutIds !== []) {
            throw ContentSystemException::defaultContentLayoutDeletion($defaultLayoutIds);
        }
    }

    /**
     * @return array<string, string> config key => entity type
     */
    private function defaultLayoutConfigKeys(): array
    {
        $keys = [];

        foreach ($this->definitionRegistry->getDefinitions() as $definition) {
            if (!$definition instanceof AbstractContentLayoutAssignableDefinition) {
                continue;
            }

            $key = $definition->getContentLayoutDefaultConfigKey();

            if ($key !== null) {
                $keys[$key] = $definition->getContentLayoutEntityType();
            }
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function fetchDefaultLayoutIds(): array
    {
        $values = $this->connection->fetchFirstColumn(
            'SELECT configuration_value FROM system_config WHERE configuration_key IN (:configKeys)',
            ['configKeys' => array_keys($this->defaultLayoutConfigKeys())],
            ['configKeys' => ArrayParameterType::STRING]
        );

        $layoutIds = [];

        foreach ($values as $value) {
            $layoutId = json_decode((string) $value, true, 512, \JSON_THROW_ON_ERROR)['_value'] ?? null;

            if (\is_string($layoutId) && $layoutId !== '') {
                $layoutIds[] = $layoutId;
            }
        }

        return $layoutIds;
    }
}
