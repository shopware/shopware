<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Type;

use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Loads document types registered by active apps from the generic app_feature storage.
 *
 * @internal
 */
#[Package('after-sales')]
final class AppDocumentTypeLoader implements ResetInterface
{
    /**
     * @var array<string, list<string>>|null
     */
    private ?array $typesByName = null;

    /**
     * @var array<string, array<string, scalar>>|null
     */
    private ?array $configByName = null;

    public function __construct(private readonly AppFeatureStorage $appFeatureStorage)
    {
    }

    /**
     * @return array<string, list<string>>
     */
    public function load(): array
    {
        $this->fetch();

        \assert($this->typesByName !== null);

        return $this->typesByName;
    }

    /**
     * @return array<string, scalar>
     */
    public function loadConfig(string $technicalName): array
    {
        $this->fetch();

        \assert($this->configByName !== null);

        return $this->configByName[$technicalName] ?? [];
    }

    public function reset(): void
    {
        $this->typesByName = null;
        $this->configByName = null;
    }

    private function fetch(): void
    {
        if ($this->typesByName !== null) {
            return;
        }

        $validFormats = array_column(DocumentFormat::cases(), 'value');

        $typesByName = [];
        $configByName = [];

        foreach ($this->appFeatureStorage->forActiveApps(AppDocumentTypeConfig::class) as $feature) {
            $config = $feature->config;
            \assert($config instanceof AppDocumentTypeConfig);

            $technicalName = $config->getName();
            $formats = array_values(array_intersect($config->getFormats(), $validFormats));

            if ($formats !== []) {
                $typesByName[$technicalName] = $formats;
            }

            $configByName[$technicalName] = $config->getConfig();
        }

        $this->typesByName = $typesByName;
        $this->configByName = $configByName;
    }
}
