<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopware\Core\Framework\Deprecation\BCChange\NewOptionalParameter;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class LockedField extends BoolField
{
    private bool $lockTranslation;

    #[NewOptionalParameter(version: 'v6.8.0', parameterName: 'lockTranslation', parameterType: 'bool', defaultValue: true)]
    public function __construct(/* bool $lockTranslation = true */)
    {
        parent::__construct('locked', 'locked');

        $this->lockTranslation = \func_num_args() > 0 ? func_get_arg(0) : true;
        $this->addFlags(new Computed());
    }

    public function lockTranslation(): bool
    {
        return $this->lockTranslation;
    }
}
