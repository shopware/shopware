<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\NumberRange\ValueGenerator;

use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;

/**
 * Dummy increment storage which uses a local array.
 * Obviously only for usage in unit tests.
 */
class IncrementArrayStorage extends AbstractIncrementStorage
{
    /**
     * @var array<string, int>
     */
    private array $states;

    public function __construct(array $states)
    {
        $this->states = $states;
    }

    public function reserve(array $config): int
    {
        if (!isset($this->states[$config['id']])) {
            return $this->states[$config['id']] = 1;
        }

        return ++$this->states[$config['id']];
    }

    public function preview(array $config): int
    {
        return ($this->states[$config['id']] ?? 0) + 1;
    }

    public function list(): array
    {
        return $this->states;
    }

    public function set(string $configurationId, int $value): void
    {
        $this->states[$configurationId] = $value;
    }

    public function getDecorated(): AbstractIncrementStorage
    {
        throw new DecorationPatternException(self::class);
    }
}
