<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Write\Command;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\System\Language\LanguageDefinition;

class WriteCommandQueue
{
    /**
     * @var string[]
     */
    private $registeredResources = [];

    /**
     * @var string[]
     */
    private $order = [];

    /**
     * @var array
     */
    private $commands;

    /**
     * @param WriteCommandInterface[] ...$commands
     */
    public function __construct(WriteCommandInterface ...$commands)
    {
        $this->commands = $commands;
    }

    /**
     * @param string $definition
     * @param string ...$identifierOrder
     */
    public function setOrder(string $definition, string ...$identifierOrder): void
    {
        if (in_array($definition, $this->registeredResources, true)) {
            return;
        }

        $this->order = $identifierOrder;

        $this->order = $this->moveTranslationAfterLanguage($this->order);

        foreach ($identifierOrder as $identifier) {
            $this->commands[$identifier] = [];
        }

        $this->registeredResources[] = $definition;
    }

    public function updateOrder(string $definition, string ...$identifierOrder): void
    {
        if (in_array($definition, $this->registeredResources, true)) {
            return;
        }

        $notAlreadyOrderedIdentifiers = [];
        foreach ($identifierOrder as $identifier) {
            if ($identifier === $definition || array_search($identifier, $this->order, true) === false) {
                $notAlreadyOrderedIdentifiers[] = $identifier;
            }
        }

        $localIndex = array_search($definition, $this->order);
        $this->order = array_merge(
            \array_slice($this->order, 0, $localIndex),
            $notAlreadyOrderedIdentifiers,
            \array_slice($this->order, $localIndex + 1)
        );

        $this->order = $this->moveTranslationAfterLanguage($this->order);

        foreach ($this->order as $identifier) {
            if (isset($this->commands[$identifier])) {
                continue;
            }

            $this->commands[$identifier] = [];
        }

        $this->registeredResources[] = $definition;
    }

    /**
     * @return string[]
     */
    public function getOrder(): array
    {
        return $this->order;
    }

    public function add(string $senderIdentification, WriteCommandInterface $command)
    {
        if (!is_array($this->commands[$senderIdentification])) {
            throw new \InvalidArgumentException(sprintf('Unable to set write command for %s, it was not beforehand registered.', $senderIdentification));
        }

        $this->commands[$senderIdentification][] = $command;
    }

    /**
     * @return WriteCommandInterface[]
     */
    public function getCommandsInOrder(): array
    {
        $result = [];
        foreach ($this->order as $identifier) {
            $commands = $this->commands[$identifier];

            /** @var WriteCommandInterface $command */
            foreach ($commands as $command) {
                if (!$command->isValid()) {
                    continue;
                }

                $result[] = $command;
            }
        }

        return $result;
    }

    /**
     * @return WriteCommandInterface[]
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    public function ensureIs(string $definition, $class): void
    {
        $commands = $this->commands[$definition];

        foreach ($commands as $command) {
            if (!$command instanceof $class) {
                throw new WriteTypeIntendException(sprintf(
                    'Expected command for "%s" to be "%s".',
                    $definition,
                    $class
                ));
            }
        }
    }

    /**
     * @param string $definition
     * @param array  $primaryKey
     *
     * @return WriteCommandInterface[]
     */
    public function getCommandsForEntity(string $definition, array $primaryKey): array
    {
        $commands = $this->getCommandsInOrder();

        $filtered = [];
        foreach ($commands as $command) {
            if ($command->getDefinition() === $definition && $command->getPrimaryKey() == $primaryKey) {
                $filtered[] = $command;
            }
        }

        return $filtered;
    }

    /**
     * @param array $order
     *
     * @return array
     */
    private function moveTranslationAfterLanguage(array $order): array
    {
        $flipped = \array_flip($order);

        if (!isset($flipped[LanguageDefinition::class])) {
            return $order;
        }

        $translations = [];

        /** @var string|EntityDefinition $definition */
        foreach ($order as $index => $definition) {
            $translations[$definition::getTranslationDefinitionClass()] = 1;
        }

        $translations = array_intersect_key($flipped, $translations);
        foreach ($translations as $definition => $index) {
            unset($order[$index]);
        }

        $order = array_values($order);

        foreach ($translations as $definition => $index) {
            $order[] = $definition;
        }

        return $order;
    }
}
