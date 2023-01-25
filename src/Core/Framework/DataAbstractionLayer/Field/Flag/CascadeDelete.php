<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopware\Core\Framework\Log\Package;

/**
 * In case the referenced association data will be deleted, the related data will be deleted too
 */
#[Package('core')]
class CascadeDelete extends Flag
{
    /**
     * @var bool
     */
    protected $cloneRelevant;

    public function __construct(bool $cloneRelevant = true)
    {
        $this->cloneRelevant = $cloneRelevant;
    }

    public function parse(): \Generator
    {
        yield 'cascade_delete' => true;
    }

    public function isCloneRelevant(): bool
    {
        return $this->cloneRelevant;
    }
}
