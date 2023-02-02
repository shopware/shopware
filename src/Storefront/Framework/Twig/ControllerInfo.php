<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

class ControllerInfo
{
    /**
     * @var string|null
     */
    private $action;

    /**
     * @var string|null
     */
    private $namespace;

    /**
     * @var string|null
     */
    private $name;

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(?string $action): void
    {
        $this->action = $action;
    }

    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    public function setNamespace(?string $namespace): void
    {
        $this->namespace = $namespace;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }
}
