<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Script;

use Shopware\Core\Framework\App\Template\TemplateLoader;

/**
 * @internal only for use by the app-system
 */
class ScriptLoader extends TemplateLoader
{
    protected const TEMPLATE_DIR = '/Resources/scripts';

    protected const ALLOWED_TEMPLATE_DIRS = [];

    protected const ALLOWED_FILE_EXTENSIONS = '*.twig';
}
