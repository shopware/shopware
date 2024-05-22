<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Feature;

use Doctrine\DBAL\Exception as DBALException;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\Event\BeforeFeatureFlagToggleEvent;
use Shopware\Core\Framework\Feature\Event\FeatureFlagToggledEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @final
 *
 * @phpstan-import-type FeatureFlagConfig from Feature
 */
#[Package('core')]
class FeatureFlagRegistry
{
    public const STORAGE_KEY = 'feature.flags';

    /**
     * @param array<string, FeatureFlagConfig> $staticFeatureFlags
     *
     * @internal
     */
    public function __construct(
        private readonly AbstractKeyValueStorage $keyValueStorage,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly array $staticFeatureFlags = [],
        private readonly bool $enabledFeatureToggle = false
    ) {
    }

    public function register(): void
    {
        $static = $this->staticFeatureFlags;

        if (!$this->enabledFeatureToggle) {
            Feature::registerFeatures($static);

            return;
        }

        try {
            $stored = $this->keyValueStorage->get(self::STORAGE_KEY, []);

            if (!empty($stored) && \is_string($stored)) {
                $stored = \json_decode($stored, true, 512, \JSON_THROW_ON_ERROR);
            }

            $stored = array_filter($stored, static function (array $flag) {
                return !\array_key_exists('major', $flag) || !$flag['major'];
            });

            $flags = array_merge($static, $stored);
        } catch (DBALException) {
            // We don't have a database connection
            $flags = $static;
        }

        Feature::registerFeatures($flags);
    }

    public function enable(string $feature): void
    {
        $this->toggle($feature, true);
    }

    public function disable(string $feature): void
    {
        $this->toggle($feature, false);
    }

    private function toggle(string $feature, bool $active): void
    {
        if (!$this->enabledFeatureToggle) {
            throw FeatureException::featureCannotBeToggled($feature);
        }

        /** @var array<string, FeatureFlagConfig> $registeredFlags */
        $registeredFlags = Feature::getRegisteredFeatures();

        if (!\array_key_exists($feature, $registeredFlags)) {
            throw FeatureException::featureNotRegistered($feature);
        }

        if (!\array_key_exists('toggleable', $registeredFlags[$feature]) || (bool) $registeredFlags[$feature]['toggleable'] === false) {
            throw FeatureException::featureCannotBeToggled($feature);
        }

        $registeredFlags[$feature] = [
            ...$registeredFlags[$feature],
            'static' => \array_key_exists($feature, $this->staticFeatureFlags),
            'active' => $active,
        ];

        $this->dispatcher->dispatch(new BeforeFeatureFlagToggleEvent($feature, $active));

        $this->keyValueStorage->set(self::STORAGE_KEY, $registeredFlags);
        Feature::setActive($feature, $active);

        $this->dispatcher->dispatch(new FeatureFlagToggledEvent($feature, $active));
    }
}
