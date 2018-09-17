<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Struct\Struct;

class Match extends Struct
{
    /**
     * @var bool
     */
    protected $match;

    /**
     * @var array
     */
    private $messages;

    /**
     * @param bool  $match
     * @param array $messages
     */
    public function __construct(bool $match, array $messages = [])
    {
        $this->match = $match;
        $this->messages = $messages;
    }

    public function __invoke()
    {
        return $this->match;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function matches(): bool
    {
        return $this->match;
    }
}
