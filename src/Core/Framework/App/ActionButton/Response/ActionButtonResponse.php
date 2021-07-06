<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ActionButton\Response;

use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal only for use by the app-system
 */
abstract class ActionButtonResponse extends Struct
{
    public const ACTION_SHOW_NOTITFICATION = 'notification';
    public const ACTION_RELOAD_DATA = 'reload';
    public const ACTION_OPEN_NEW_TAB = 'openNewTab';
    public const ACTION_OPEN_MODAL = 'openModal';

    public string $actionType;

    final public function __construct(string $actionType)
    {
        $this->actionType = $actionType;
    }

    abstract public function validate(string $actionId): void;

    public static function create(string $actionId, string $actionType, array $data): self
    {
        $response = new static($actionType);
        $response->assign($data);
        $response->validate($actionId);

        return $response;
    }
}
