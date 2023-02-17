<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Api;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Api\ScriptResponse;

#[Package('core')]
interface ResponseHook
{
    public function getName(): string;

    /**
     * @internal
     */
    public function getScriptResponse(): ScriptResponse;

    public function setResponse(ScriptResponse $scriptResponse): void;
}
