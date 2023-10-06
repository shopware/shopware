<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Hook;

#[Package('core')]
class HookInjectionException extends \RuntimeException
{
    public function __construct(
        Hook $hook,
        string $class,
        string $required
    ) {
        parent::__construct(sprintf(
            'Class %s is only executable in combination with hooks that implement the %s interface. Hook %s does not implement this interface',
            $class,
            $required,
            $hook->getName()
        ));
    }
}
