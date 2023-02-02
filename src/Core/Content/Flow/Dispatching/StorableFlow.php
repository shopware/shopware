<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Shopware\Core\Content\Flow\FlowException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Feature;

/**
 * @internal
 */
class StorableFlow
{
    protected ?FlowState $state = null;

    /**
     * @var array<string, mixed>
     */
    protected array $config = [];

    protected string $name;

    protected Context $context;

    /**
     * @var array<string, mixed>
     */
    protected array $store = [];

    /**
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * @deprecated tag:v6.5.0 Will be removed
     */
    private ?FlowEventAware $originalEvent = null;

    /**
     * @deprecated tag:v6.5.0 Will be removed
     */
    private ?FlowEvent $flowEvent = null;

    /**
     * @param array<string, mixed> $store
     * @param array<string, mixed> $data
     */
    public function __construct(
        string $name,
        Context $context,
        array $store = [],
        array $data = []
    ) {
        $this->name = $name;
        $this->context = $context;
        $this->data = $data;
        $this->store = $store;
    }

    /**
     * @deprecated tag:v6.5.0 Will be removed
     */
    public function setOriginalEvent(FlowEventAware $event): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        $this->originalEvent = $event;
    }

    /**
     * @deprecated tag:v6.5.0 Will be removed
     */
    public function getOriginalEvent(): ?FlowEventAware
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return $this->originalEvent;
    }

    /**
     * @deprecated tag:v6.5.0 Will be removed
     */
    public function setFlowEvent(FlowEvent $event): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        $this->flowEvent = $event;
    }

    /**
     * @deprecated tag:v6.5.0 Will be removed
     */
    public function getFlowEvent(): ?FlowEvent
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return $this->flowEvent;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @param mixed $value
     */
    public function setStore(string $key, $value): void
    {
        $this->store[$key] = $value;
    }

    public function hasStore(string $key): bool
    {
        return \array_key_exists($key, $this->store);
    }

    /**
     * @param mixed $default
     *
     * @return mixed
     */
    public function getStore(string $key, $default = null)
    {
        return $this->store[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function stored(): array
    {
        return $this->store;
    }

    /**
     * @param mixed $value
     */
    public function setData(string $key, $value): void
    {
        $this->data[$key] = $value;
    }

    public function hasData(string $key): bool
    {
        return \array_key_exists($key, $this->data);
    }

    /**
     * @param mixed $default
     *
     * @return mixed
     */
    public function getData(string $key, $default = null)
    {
        $value = $this->data[$key] ?? $default;

        if (\is_callable($value)) {
            $this->data[$key] = $value($this);
        }

        return $this->data[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        foreach ($this->data as $key => $data) {
            $this->getData($key);
        }

        return $this->data;
    }

    /**
     * @param array<int, mixed> $args
     */
    public function lazy(string $key, callable $closure, array $args): void
    {
        $this->data[$key] = $closure($args);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    public function setFlowState(FlowState $state): void
    {
        $this->state = $state;
    }

    public function getFlowState(): FlowState
    {
        if (!$this->state) {
            throw FlowException::methodNotCompatible('getFlowState()', self::class);
        }

        return $this->state;
    }

    public function stop(): void
    {
        if (!$this->state) {
            throw FlowException::methodNotCompatible('stop()', self::class);
        }

        $this->state->stop = true;
    }
}
