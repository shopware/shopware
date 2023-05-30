<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Requirements\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @internal
 *
 * @extends Collection<RequirementCheck>
 */
#[Package('core')]
class RequirementsCheckCollection extends Collection
{
    public function getPathChecks(): self
    {
        return $this->filterInstance(PathCheck::class);
    }

    public function getSystemChecks(): self
    {
        return $this->filterInstance(SystemCheck::class);
    }

    public function hasError(): bool
    {
        return $this->filter(static fn (RequirementCheck $check): bool => $check->getStatus() === RequirementCheck::STATUS_ERROR)->first() !== null;
    }

    public function hasPathError(): bool
    {
        return $this->getPathChecks()->hasError();
    }

    public function hasSystemError(): bool
    {
        return $this->getSystemChecks()->hasError();
    }

    protected function getExpectedClass(): ?string
    {
        return RequirementCheck::class;
    }
}
