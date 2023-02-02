<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ActionButton\Response;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class OpenModalResponse extends ActionButtonResponse
{
    final public const ACTION_TYPE = 'openModal';

    /**
     * This is the embedded link that the user want to embed in the iframe after the action has been taken.
     */
    protected string $iframeUrl = '';

    /**
     * This is size of modal.
     */
    protected string $size = '';

    /**
     * This is expansion of modal.
     */
    protected bool $expand = false;

    public function __construct()
    {
        parent::__construct(self::ACTION_TYPE);
    }
}
