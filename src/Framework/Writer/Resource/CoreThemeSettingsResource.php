<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class CoreThemeSettingsResource extends Resource
{
    protected const COMPILER_FORCE_FIELD = 'compilerForce';
    protected const COMPILER_CREATE_SOURCE_MAP_FIELD = 'compilerCreateSourceMap';
    protected const COMPILER_COMPRESS_CSS_FIELD = 'compilerCompressCss';
    protected const COMPILER_COMPRESS_JS_FIELD = 'compilerCompressJs';
    protected const FORCE_RELOAD_SNIPPETS_FIELD = 'forceReloadSnippets';

    public function __construct()
    {
        parent::__construct('s_core_theme_settings');

        $this->fields[self::COMPILER_FORCE_FIELD] = (new IntField('compiler_force'))->setFlags(new Required());
        $this->fields[self::COMPILER_CREATE_SOURCE_MAP_FIELD] = (new IntField('compiler_create_source_map'))->setFlags(new Required());
        $this->fields[self::COMPILER_COMPRESS_CSS_FIELD] = (new IntField('compiler_compress_css'))->setFlags(new Required());
        $this->fields[self::COMPILER_COMPRESS_JS_FIELD] = (new IntField('compiler_compress_js'))->setFlags(new Required());
        $this->fields[self::FORCE_RELOAD_SNIPPETS_FIELD] = new IntField('force_reload_snippets');
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\CoreThemeSettingsResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): ?\Shopware\Framework\Event\CoreThemeSettingsWrittenEvent
    {
        if (empty($updates) || !array_key_exists(self::class, $updates)) {
            return null;
        }

        $event = new \Shopware\Framework\Event\CoreThemeSettingsWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        $event->addEvent(\Shopware\Framework\Write\Resource\CoreThemeSettingsResource::createWrittenEvent($updates, $context));

        return $event;
    }
}
