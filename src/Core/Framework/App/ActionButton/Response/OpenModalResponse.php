<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ActionButton\Response;

use Shopware\Core\Framework\App\Exception\ActionProcessException;

/**
 * @internal only for use by the app-system
 */
class OpenModalResponse extends ActionButtonResponse
{
    private const VALID_MODAL_SIZES = [
        'small',
        'medium',
        'large',
        'fullscreen',
    ];

    /**
     * This is the embedded link that the user want to embed in the iframe after the action has been taken.
     */
    public string $iframeUrl = '';

    /**
     * This is size of modal.
     */
    public string $size = '';

    /**
     * This is expansion of modal.
     */
    public bool $expand = false;

    public function validate(string $actionId): void
    {
        if (empty($this->iframeUrl)) {
            throw new ActionProcessException($actionId, 'Invalid iframe url provided by App');
        }

        if (!\in_array($this->size, self::VALID_MODAL_SIZES, true)) {
            throw new ActionProcessException($actionId, 'The size is invalid');
        }
    }
}
