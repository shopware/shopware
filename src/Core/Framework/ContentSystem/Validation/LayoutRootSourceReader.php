<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Validation;

use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Reads a content layout's immutable root source at write time: the in-flight write batch first (so an atomic
 * create-and-bind sees the layout's INSERT payload), then the committed row.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class LayoutRootSourceReader
{
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry,
    ) {
    }

    /**
     * @param list<WriteCommand> $commands the current write batch (from PreWriteValidationEvent)
     */
    public function read(mixed $contentLayoutId, array $commands, Context $context): ?string
    {
        $layoutId = $this->normalizeId($contentLayoutId);

        if ($layoutId === null) {
            return null;
        }

        return $this->fromCommands($layoutId, $commands) ?? $this->fromStore($layoutId, $context);
    }

    private function normalizeId(mixed $contentLayoutId): ?string
    {
        if (!\is_string($contentLayoutId) || $contentLayoutId === '') {
            return null;
        }

        // The 16-byte branch normalizes the binary FK payload the assignment validators pass, while the write
        // validator already passes the decoded hex primary key. Same idiom as ProductStream's
        // ProductStreamWriteResultHelper::normalizeStreamId (which additionally Uuid::isValid-guards) — consolidate
        // into a shared Uuid helper, reconciling that guard difference, if a third copy appears.
        return \strlen($contentLayoutId) === 16 ? Uuid::fromBytesToHex($contentLayoutId) : $contentLayoutId;
    }

    /**
     * The root source of a layout written in the same batch. Returns null when the layout is not in the batch or
     * the matching command does not set root_source (only an INSERT does, since the field is immutable), in which
     * case the committed-store fallback decides.
     *
     * @param list<WriteCommand> $commands
     */
    private function fromCommands(string $layoutId, array $commands): ?string
    {
        foreach ($commands as $command) {
            if ($command->getEntityName() !== ContentLayoutDefinition::ENTITY_NAME || $command instanceof DeleteCommand) {
                continue;
            }

            if (($command->getDecodedPrimaryKey()['id'] ?? null) !== $layoutId) {
                continue;
            }

            if (!$command->hasField(ContentLayoutDefinition::ROOT_SOURCE_FIELD)) {
                continue;
            }

            $value = $command->getPayload()[ContentLayoutDefinition::ROOT_SOURCE_FIELD];

            return \is_string($value) ? $value : null;
        }

        return null;
    }

    private function fromStore(string $layoutId, Context $context): ?string
    {
        $layout = $this->definitionRegistry->getRepository(ContentLayoutDefinition::ENTITY_NAME)->search(new Criteria([$layoutId]), $context)->getEntities()->first();

        return $layout instanceof ContentLayoutEntity ? $layout->getRootSource() : null;
    }
}
