<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Notification;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlist;
use Shopware\Core\System\Integration\IntegrationDefinition;
use Shopware\Core\System\User\UserDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * Announces an edited MCP allowlist of an integration or a user as a list change, so connected clients
 * re-read the lists the allowlist narrows. Listens on the DAL write rather than on the allowlist
 * controllers, so every write path counts: the allowlist actions, `PATCH /api/integration/{id}`,
 * `PATCH /api/user/{id}`, the Sync API and direct repository writes.
 *
 * Only updates count, and only for the lists whose entry changed. A new integration or user has no
 * connected client yet, and a deleted one loses its access altogether.
 */
#[Package('framework')]
class McpAllowlistChangeSubscriber implements EventSubscriberInterface
{
    private const FIELD = 'mcp_allowlist';

    private const ENTITIES = [IntegrationDefinition::ENTITY_NAME, UserDefinition::ENTITY_NAME];

    public function __construct(private readonly McpListChangedNotifier $notifier)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [EntityWriteEvent::class => 'onWrite'];
    }

    public function onWrite(EntityWriteEvent $event): void
    {
        $commands = [];
        foreach (self::ENTITIES as $entity) {
            foreach ($event->getCommandsForEntity($entity) as $command) {
                if ($command instanceof UpdateCommand && $command->hasField(self::FIELD)) {
                    $command->requestChangeSet();
                    $commands[] = $command;
                }
            }
        }

        if ($commands === []) {
            return;
        }

        $event->addSuccess(function () use ($commands): void {
            $changes = McpListChangedNotificationSet::none();

            foreach ($commands as $command) {
                $changeSet = $command->getChangeSet();
                if ($changeSet === null) {
                    continue;
                }

                $changes = $changes->merge(self::changedLists($changeSet->getBefore(self::FIELD), $changeSet->getAfter(self::FIELD)));
            }

            $this->notifier->notify($changes);
        });
    }

    /**
     * Compares the decoded entries, so a value written again with other whitespace or key order is no change.
     */
    private static function changedLists(mixed $before, mixed $after): McpListChangedNotificationSet
    {
        $before = self::decode($before);
        $after = self::decode($after);

        $moved = static fn (string $list): bool => ($before[$list] ?? null) !== ($after[$list] ?? null);

        return new McpListChangedNotificationSet(
            tools: $moved(McpAllowlist::TOOLS),
            resources: $moved(McpAllowlist::RESOURCES),
            prompts: $moved(McpAllowlist::PROMPTS),
        );
    }

    /**
     * @return array<mixed>
     */
    private static function decode(mixed $value): array
    {
        if (\is_array($value)) {
            return $value;
        }

        if (!\is_string($value) || $value === '') {
            return [];
        }

        try {
            $decoded = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return \is_array($decoded) ? $decoded : [];
    }
}
