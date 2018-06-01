<?php declare(strict_types=1);
/**
 * Shopware\Core 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware\Core" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Core\Framework\ORM\Write\Command;

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
     * @param string   $definition
     * @param string[] ...$identifierOrder
     */
    public function setOrder(string $definition, string ...$identifierOrder): void
    {
        if (in_array($definition, $this->registeredResources, true)) {
            return;
        }

        $this->order = $identifierOrder;

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
            if ($identifier === $definition || array_search($identifier, $this->order) === false) {
                $notAlreadyOrderedIdentifiers[] = $identifier;
            }
        }

        $localIndex = array_search($definition, $this->order);
        $this->order = array_merge(
            array_slice($this->order, 0, $localIndex),
            $notAlreadyOrderedIdentifiers,
            array_slice($this->order, $localIndex + 1)
        );

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

        return array_filter(
            $commands,
            function (WriteCommandInterface $command) use ($definition, $primaryKey) {
                return $command->getDefinition() === $definition && $command->getPrimaryKey() == $primaryKey;
            }
        );
    }
}
