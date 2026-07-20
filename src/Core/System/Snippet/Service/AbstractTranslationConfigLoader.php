<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Service;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;

#[Package('discovery')]
abstract class AbstractTranslationConfigLoader
{
    abstract public function getDecorated(): AbstractTranslationConfigLoader;

    abstract public function load(): TranslationConfig;
}
