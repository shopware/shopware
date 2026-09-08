<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset\Serialization;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\LayoutPresetPayloadCompiler;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\ContentSystemLayoutPresetSpecification;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class LayoutPresetSerializer
{
    public function __construct(
        private readonly LayoutPresetPayloadCompiler $compiler,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function denormalize(array $data, string $id): ContentSystemLayoutPresetSpecification
    {
        $this->validate($data);

        /** @var string $name */
        $name = $data['name'];
        $description = $data['description'] ?? null;
        $icon = $data['icon'] ?? null;
        /** @var list<mixed> $layout */
        $layout = $data['layout'];

        return new ContentSystemLayoutPresetSpecification(
            $id,
            $name,
            \is_string($description) ? $description : null,
            \is_string($icon) ? $icon : null,
            $this->compiler->compile($layout),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function validate(array $data): void
    {
        $name = $data['name'] ?? null;
        if (!\is_string($name) || $name === '') {
            throw ContentSystemException::layoutPresetInvalid('The "name" field is required and must be a non-empty string.');
        }

        $description = $data['description'] ?? null;
        if ($description !== null && !\is_string($description)) {
            throw ContentSystemException::layoutPresetInvalid('The "description" field must be a string.');
        }

        $icon = $data['icon'] ?? null;
        if ($icon !== null && !\is_string($icon)) {
            throw ContentSystemException::layoutPresetInvalid('The "icon" field must be a string.');
        }

        $layout = $data['layout'] ?? null;
        if (!\is_array($layout) || !array_is_list($layout)) {
            throw ContentSystemException::layoutPresetInvalid('The "layout" field is required and must be a list of elements.');
        }
    }
}
