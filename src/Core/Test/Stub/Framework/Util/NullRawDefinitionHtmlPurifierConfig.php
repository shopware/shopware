<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\Framework\Util;

use Shopware\Core\Framework\Log\Package;

/**
 * Returns null when `getHTMLDefinition(true)` is called to exercise the
 * defensive null-check in HtmlSanitizer::getConfig(); delegates all other
 * calls to the real implementation so purification can still complete.
 *
 * @internal
 */
#[Package('framework')]
class NullRawDefinitionHtmlPurifierConfig extends \HTMLPurifier_Config
{
    public int $rawDefinitionCalls = 0;

    public function __construct()
    {
        parent::__construct(\HTMLPurifier_ConfigSchema::instance());
    }

    public function getHTMLDefinition($raw = false, $optimized = false)
    {
        if ($raw === true) {
            ++$this->rawDefinitionCalls;

            return null;
        }

        return parent::getHTMLDefinition($raw, $optimized);
    }
}
