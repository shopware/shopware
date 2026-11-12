<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\Dto\LayoutPresetSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Validation\ContentSystemLayoutPresetValidator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal only for use by the app-system
 *
 * @final
 */
#[Package('framework')]
class ContentSystemLayoutPresetAppLoader
{
    public function __construct(
        private readonly YamlLayoutPresetLoader $yamlLoader,
        private readonly DatabaseLayoutPresetLoader $databaseLoader,
        private readonly ContentSystemLayoutPresetValidator $presetValidator,
    ) {
    }

    /**
     * @return array<string, LayoutPresetSpecificationDto> the app's presets keyed by resolved id
     */
    public function load(string $directory, string $appName): array
    {
        $presets = $this->yamlLoader->loadDtosFromDirectory($directory, 'app:' . $appName, $appName);

        $installed = array_filter(
            $this->databaseLoader->load(),
            static fn (string $id): bool => !str_starts_with($id, $appName . ':'),
            \ARRAY_FILTER_USE_KEY,
        );
        $available = [...$this->yamlLoader->load(), ...$installed, ...$presets];

        $violations = new ConstraintViolationList();
        foreach (array_keys($presets) as $id) {
            $violations->addAll($this->presetValidator->validate($id, $available));
        }

        if ($violations->count() > 0) {
            throw ContentSystemException::layoutPresetsInvalid($violations);
        }

        return $presets;
    }
}
