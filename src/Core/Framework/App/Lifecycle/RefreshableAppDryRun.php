<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class RefreshableAppDryRun extends AbstractAppLifecycle
{
    /**
     * @var Manifest[]
     */
    private $toBeInstalled = [];

    /**
     * @var Manifest[]
     */
    private $toBeUpdated = [];

    /**
     * @var string[]
     */
    private $toBeDeleted = [];

    public function getDecorated(): AbstractAppLifecycle
    {
        throw new DecorationPatternException(self::class);
    }

    public function install(Manifest $manifest, bool $activate, Context $context): void
    {
        $this->toBeInstalled[] = $manifest;
    }

    public function update(Manifest $manifest, array $app, Context $context): void
    {
        $this->toBeUpdated[] = $manifest;
    }

    public function delete(string $appName, array $app, Context $context, bool $keepUserData = false): void
    {
        $this->toBeDeleted[] = $appName;
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
     * @return string[]
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
}
