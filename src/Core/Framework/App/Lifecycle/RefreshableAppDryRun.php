<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
class RefreshableAppDryRun extends AbstractAppLifecycle
{
    /**
     * @var Manifest[]
     */
    private array $toBeInstalled = [];

    /**
     * @var Manifest[]
     */
    private array $toBeUpdated = [];

    /**
     * @var array<string>
     */
    private array $toBeDeleted = [];

    public function getDecorated(): AbstractAppLifecycle
    {
        throw new DecorationPatternException(self::class);
    }

    public function filter(array $names): self
    {
        $filter = static function (string $appName) use ($names) {
            foreach ($names as $name) {
                if (str_contains($appName, $name)) {
                    return true;
                }

                return false;
            }

            return false;
        };

        $apps = clone $this;
        $apps->toBeDeleted = array_filter($apps->toBeDeleted, $filter, \ARRAY_FILTER_USE_KEY);
        $apps->toBeInstalled = array_filter($apps->toBeInstalled, $filter, \ARRAY_FILTER_USE_KEY);
        $apps->toBeUpdated = array_filter($apps->toBeUpdated, $filter, \ARRAY_FILTER_USE_KEY);

        return $apps;
    }

    public function install(Manifest $manifest, bool $activate, Context $context): void
    {
        $this->toBeInstalled[$manifest->getMetadata()->getName()] = $manifest;
    }

    public function update(Manifest $manifest, array $app, Context $context): void
    {
        $this->toBeUpdated[$manifest->getMetadata()->getName()] = $manifest;
    }

    public function delete(string $appName, array $app, Context $context, bool $keepUserData = false): void
    {
        $this->toBeDeleted[$appName] = $appName;
    }

    /**
     * @return Manifest[]
     */
    public function getToBeInstalled(): array
    {
        return $this->toBeInstalled;
    }

    /**
     * @return Manifest[]
     */
    public function getToBeUpdated(): array
    {
        return $this->toBeUpdated;
    }

    /**
     * @return array<string>
     */
    public function getToBeDeleted(): array
    {
        return $this->toBeDeleted;
    }

    public function isEmpty(): bool
    {
        return \count($this->toBeInstalled) === 0
            && \count($this->toBeUpdated) === 0
            && \count($this->toBeDeleted) === 0;
    }

    /**
     * @return array<string>
     */
    public function getAppNames(): array
    {
        return [...array_keys($this->toBeInstalled), ...array_keys($this->toBeUpdated), ...array_keys($this->toBeDeleted)];
    }
}
