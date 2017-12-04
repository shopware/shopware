<?php declare(strict_types=1);

namespace Shopware\Storefront\Twig;

class ControllerInfo
{
    /**
     * @var string|null
     */
    private $action = null;

    /**
     * @var string|null
     */
    private $namespace = null;

    /**
     * @var string|null
     */
    private $name = null;

    /**
     * @return null|string
     */
    public function getAction(): ?string
    {
        return $this->action;
    }

    /**
     * @param null|string $action
     */
    public function setAction(?string $action)
    {
        $this->action = $action;
    }

    /**
     * @return null|string
     */
    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    /**
     * @param null|string $namespace
     */
    public function setNamespace(?string $namespace)
    {
        $this->namespace = $namespace;
    }

    /**
     * @return null|string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param null|string $name
     */
    public function setName(?string $name)
    {
        $this->name = $name;
    }
}
