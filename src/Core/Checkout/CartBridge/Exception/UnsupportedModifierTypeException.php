<?php declare(strict_types=1);

namespace Shopware\Checkout\CartBridge\Exception;

class UnsupportedModifierTypeException extends \Exception
{
    public const CODE = 5000;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $class;

    public function __construct(string $type, string $class)
    {
        $this->type = $type;
        $this->class = $class;
        parent::__construct(
            sprintf('Unsupported type %s in %s', $this->type, $this->class),
            self::CODE
        );
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getClass(): string
    {
        return $this->class;
    }
}
