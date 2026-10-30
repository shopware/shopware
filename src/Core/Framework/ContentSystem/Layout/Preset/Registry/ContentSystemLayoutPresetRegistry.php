<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset\Registry;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\LayoutPresetPayloadCompiler;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader\AbstractContentSystemLayoutPresetLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\ContentSystemLayoutPresetSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Validation\ContentSystemLayoutPresetValidator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[Package('framework')]
class ContentSystemLayoutPresetRegistry extends AbstractContentSystemLayoutPresetRegistry
{
    /**
     * @param iterable<AbstractContentSystemLayoutPresetLoader> $loaders
     */
    public function __construct(
        private readonly iterable $loaders,
        private readonly LayoutPresetPayloadCompiler $compiler,
        private readonly ContentSystemLayoutPresetValidator $validator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getDecorated(): AbstractContentSystemLayoutPresetRegistry
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @return array<string, ContentSystemLayoutPresetSpecification>
     */
    public function all(): array
    {
        $dtos = [];

        foreach ($this->loaders as $loader) {
            foreach ($loader->load() as $id => $dto) {
                if (\array_key_exists($id, $dtos)) {
                    throw ContentSystemException::layoutPresetDuplicate($id);
                }

                $dtos[$id] = $dto;
            }
        }

        $presets = [];
        $presetViolations = [];

        foreach ($dtos as $id => $dto) {
            $violations = $this->validator->validate($id, $dtos);

            if ($violations->count() > 0) {
                array_push($presetViolations, ...$violations);

                continue;
            }

            $presets[$id] = new ContentSystemLayoutPresetSpecification(
                $id,
                $dto->name,
                $dto->description,
                $dto->icon,
                $this->compiler->compile($dto->layout, $dtos, [$id]),
                $dto->category,
                $dto->priority,
                $dto->illustration,
            );
        }

        foreach ($presetViolations as $violation) {
            $this->logger->warning((string) $violation->getMessage());
        }

        return $presets;
    }

    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->all());
    }

    public function get(string $id): ContentSystemLayoutPresetSpecification
    {
        return $this->all()[$id] ?? throw ContentSystemException::layoutPresetNotFound($id);
    }
}
