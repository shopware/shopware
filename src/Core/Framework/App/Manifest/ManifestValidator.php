<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Webhook\Hookable\HookableValidator;

class ManifestValidator
{
    /**
     * @var HookableValidator
     */
    private $hookableValidator;

    public function __construct(HookableValidator $hookableValidator)
    {
        $this->hookableValidator = $hookableValidator;
    }

    public function validate(Manifest $manifest, Context $context): void
    {
        $this->hookableValidator->validate($manifest, $context);
    }
}
