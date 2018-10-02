<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\Exception;

class InvalidMatchContextException extends \Exception
{
    public const CODE = 200004;

    /**
     * @var string
     */
    protected $context;

    /**
     * @var string
     */
    protected $class;

    public function __construct(string $context, string $class)
    {
        $this->context = $context;
        $this->class = $class;
        parent::__construct(
            sprintf('Invalid match context %s in %s', $this->context, $this->class),
            self::CODE
        );
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function getClass(): string
    {
        return $this->class;
    }
}
