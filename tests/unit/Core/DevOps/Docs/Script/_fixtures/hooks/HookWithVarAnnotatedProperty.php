<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs\Script\_fixtures\hooks;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Script\Execution\Hook;

/**
 * Hook with a var-annotated untyped property.
 *
 * @hook-use-case data_loading
 *
 * @since 6.4.0.0
 */
class HookWithVarAnnotatedProperty extends Hook
{
    final public const HOOK_NAME = 'var-annotated-hook';

    /**
     * @var string
     */
    public $varAnnotatedProp = 'value';

    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public static function getServiceIds(): array
    {
        return [];
    }
}
