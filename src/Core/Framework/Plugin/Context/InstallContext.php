<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Context;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin;

class InstallContext implements \JsonSerializable
{
    public const CACHE_TAG_TEMPLATE = 'template';
    public const CACHE_TAG_CONFIG = 'config';
    public const CACHE_TAG_ROUTER = 'router';
    public const CACHE_TAG_PROXY = 'proxy';
    public const CACHE_TAG_THEME = 'theme';
    public const CACHE_TAG_HTTP = 'http';

    /**
     * pre defined list to invalidate simple caches
     */
    public const CACHE_LIST_DEFAULT = [
        self::CACHE_TAG_TEMPLATE,
        self::CACHE_TAG_CONFIG,
        self::CACHE_TAG_ROUTER,
        self::CACHE_TAG_PROXY,
    ];

    /**
     * pre defined list to invalidate required frontend caches
     */
    public const CACHE_LIST_FRONTEND = [
        self::CACHE_TAG_TEMPLATE,
        self::CACHE_TAG_THEME,
        self::CACHE_TAG_HTTP,
    ];

    /**
     * pre defined list to invalidate all caches
     */
    public const CACHE_LIST_ALL = [
        self::CACHE_TAG_TEMPLATE,
        self::CACHE_TAG_CONFIG,
        self::CACHE_TAG_ROUTER,
        self::CACHE_TAG_PROXY,
        self::CACHE_TAG_THEME,
        self::CACHE_TAG_HTTP,
    ];

    /**
     * @var Plugin
     */
    private $plugin;

    /**
     * @var array
     */
    private $scheduled = [];

    /**
     * @var string
     */
    private $currentVersion;

    /**
     * @var string
     */
    private $shopwareVersion;

    /**
     * @var Context
     */
    private $context;

    public function __construct(Plugin $plugin, Context $context, string $shopwareVersion, string $currentVersion)
    {
        $this->plugin = $plugin;
        $this->currentVersion = $currentVersion;
        $this->shopwareVersion = $shopwareVersion;
        $this->context = $context;
    }

    /**
     * @return string
     */
    public function getCurrentVersion(): string
    {
        return $this->currentVersion;
    }

    public function assertMinimumVersion(string $requiredVersion): bool
    {
        if ($this->shopwareVersion === '___VERSION___') {
            return true;
        }

        return version_compare($this->shopwareVersion, $requiredVersion, '>=');
    }

    public function scheduleMessage(string $message): void
    {
        $this->scheduled['message'] = $message;
    }

    /**
     * Adds the defer task to clear the frontend cache
     *
     * @param string[] $caches
     */
    public function scheduleClearCache(array $caches): void
    {
        if (!array_key_exists('cache', $this->scheduled)) {
            $this->scheduled['cache'] = [];
        }
        $this->scheduled['cache'] = array_values(array_unique(array_merge($this->scheduled['cache'], $caches)));
    }

    /**
     * @return string[]
     */
    public function getScheduled(): array
    {
        return $this->scheduled;
    }

    /**
     * @return Plugin
     */
    public function getPlugin(): Plugin
    {
        return $this->plugin;
    }

    public function jsonSerialize(): array
    {
        return ['scheduled' => $this->scheduled];
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
