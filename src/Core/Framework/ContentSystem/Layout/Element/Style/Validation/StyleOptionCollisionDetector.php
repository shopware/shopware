<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Validation;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class StyleOptionCollisionDetector
{
    public function __construct(
        private readonly AbstractContentSystemStyleOptionRegistry $registry,
    ) {
    }

    /**
     * Simulates whether proposed style option names can be integrated into the current registry
     * state without collisions.
     *
     * Note: this is a best-effort check with a TOCTOU window. The registry snapshot is read before
     * the DB write, so two concurrent app installs proposing the same name can both pass validation.
     * The UNIQUE KEY on `app_content_system_style_option.name` acts as the authoritative guard; this
     * application-level check exists solely to provide a descriptive error message for the common
     * (non-concurrent) case.
     *
     * @param array<string, string> $proposed name => source label — keyed by name, so intra-source
     *                                        duplicates are implicitly deduplicated by the caller.
     *                                        This method does not detect them.
     * @param array<string, string> $additionalRegistered name => source label (entries not in all()
     *                                                    but treated as occupied, e.g. inactive app options)
     */
    public function validate(
        array $proposed,
        ?string $excludeSource,
        array $additionalRegistered,
    ): void {
        $existing = $this->registry->all();

        foreach ($proposed as $name => $source) {
            // Skip entries owned by the caller's source to avoid self-collision during updates
            if (\array_key_exists($name, $existing)
                && ($excludeSource === null || $existing[$name]->source() !== $excludeSource)
            ) {
                throw ContentSystemException::styleOptionDuplicate(
                    $name,
                    $existing[$name]->source(),
                    $source
                );
            }

            // Inactive options are excluded from all() but still occupy name space
            if (\array_key_exists($name, $additionalRegistered)) {
                throw ContentSystemException::styleOptionDuplicate(
                    $name,
                    $additionalRegistered[$name],
                    $source
                );
            }
        }
    }
}
