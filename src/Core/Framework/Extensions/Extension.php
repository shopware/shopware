<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Extensions;

use Psr\EventDispatcher\StoppableEventInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ExtendableTrait;

/**
 * @template TResultType
 */
#[Package('framework')]
abstract class Extension implements StoppableEventInterface
{
    use ExtendableTrait;

    /**
     * @var TResultType
     */
    public mixed $result = null;

    public ?\Throwable $exception = null;

    private bool $propagationStopped = false;

    /**
     * Event name dispatched before the extended operation runs.
     *
     * Subscribe via:
     *
     *     return [MyExtension::onPre() => 'replace'];
     *
     * Subscribers receive the Extension instance, may mutate its public properties, may populate
     * `$extension->result` and call `stopPropagation()` to short-circuit the operation.
     *
     * Subclasses must declare `public const NAME = '...';` — this helper uses late static binding to read it.
     */
    public static function onPre(): string
    {
        return ExtensionDispatcher::pre(self::getName());
    }

    /**
     * Event name dispatched after the operation (or a subscriber-supplied replacement) has produced a result.
     * Use this to inspect or mutate `$extension->result` after the fact.
     */
    public static function onPost(): string
    {
        return ExtensionDispatcher::post(self::getName());
    }

    /**
     * Event name dispatched when the operation threw. The throwable is available via `$extension->exception`.
     * Subscribers may assign a fallback `$extension->result` to swallow the exception; otherwise it is rethrown.
     */
    public static function onError(): string
    {
        return ExtensionDispatcher::error(self::getName());
    }

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
        unset($data['exception']);
        unset($data['extensions']);

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

    private static function getName(): string
    {
        /** @phpstan-ignore classConstant.notFound (constant definition in child class is enforced by static analysis) */
        return static::NAME;
    }
}
