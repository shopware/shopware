<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\Resource;

use Shopware\Framework\Api2\ApiFlag\Required;
use Shopware\Framework\Api2\Field\FkField;
use Shopware\Framework\Api2\Field\IntField;
use Shopware\Framework\Api2\Field\ReferenceField;
use Shopware\Framework\Api2\Field\StringField;
use Shopware\Framework\Api2\Field\BoolField;
use Shopware\Framework\Api2\Field\DateField;
use Shopware\Framework\Api2\Field\SubresourceField;
use Shopware\Framework\Api2\Field\LongTextField;
use Shopware\Framework\Api2\Field\LongTextWithHtmlField;
use Shopware\Framework\Api2\Field\FloatField;
use Shopware\Framework\Api2\Field\TranslatedField;
use Shopware\Framework\Api2\Field\UuidField;
use Shopware\Framework\Api2\Resource\ApiResource;

class CoreThemeSettingsResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_core_theme_settings');
        
        $this->fields['compilerForce'] = (new IntField('compiler_force'))->setFlags(new Required());
        $this->fields['compilerCreateSourceMap'] = (new IntField('compiler_create_source_map'))->setFlags(new Required());
        $this->fields['compilerCompressCss'] = (new IntField('compiler_compress_css'))->setFlags(new Required());
        $this->fields['compilerCompressJs'] = (new IntField('compiler_compress_js'))->setFlags(new Required());
        $this->fields['forceReloadSnippets'] = new IntField('force_reload_snippets');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\CoreThemeSettingsResource::class
        ];
    }
}
