<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Extensions;

use Psr\EventDispatcher\StoppableEventInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @template TResultType
 *
 * @experimental stableVersion:v6.7.0 feature:EXTENSION_SYSTEM
 */
#[Package('core')]
abstract class Extension implements StoppableEventInterface
{
    /**
     * @var TResultType
     */
    public mixed $result = null;

    private bool $propagationStopped = false;

    /**
     * @return TResultType
     */
    public function result()
    {
        return $this->result;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        $data = get_object_vars($this);
        unset($data['result']);
        unset($data['propagationStopped']);

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * Stops the propagation of the event to further event listeners.
     *
     * If multiple event listeners are connected to the same event, no
     * further event listener will be triggered once any trigger calls
     * stopPropagation().
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    /**
     * @internal
     */
    public function resetPropagation(): void
    {
        $this->propagationStopped = false;
    }
}
