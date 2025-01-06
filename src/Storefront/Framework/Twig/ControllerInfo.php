<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.7.0 - Will be removed without replacement
 */
#[Package('storefront')]
class ControllerInfo
{
    private ?string $action = null;

    private ?string $namespace = null;

    private ?string $name = null;

    public function getAction(): ?string
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0'));

        return $this->action;
    }

    public function setAction(?string $action): void
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0'));
        $this->action = $action;
    }

    public function getNamespace(): ?string
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0'));

        return $this->namespace;
    }

    public function setNamespace(?string $namespace): void
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0'));
        $this->namespace = $namespace;
    }

    public function getName(): ?string
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0'));

        return $this->name;
    }

    public function setName(?string $name): void
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0'));
        $this->name = $name;
    }
}
