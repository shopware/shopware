<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\CoreThemeSettingsWrittenEvent;

class CoreThemeSettingsWriteResource extends WriteResource
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
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): CoreThemeSettingsWrittenEvent
    {
        $event = new CoreThemeSettingsWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        /**
         * @var WriteResource
         * @var string[]      $identifiers
         */
        foreach ($updates as $class => $identifiers) {
            if (!array_key_exists($class, $updates) || count($updates[$class]) === 0) {
                continue;
            }

            $event->addEvent($class::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
