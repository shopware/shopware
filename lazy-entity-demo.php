<?php declare(strict_types=1);

/**
 * Prototype demo: PartialEntity replaced by PHP 8.4 lazy ghost objects of the real entity classes.
 *
 * Run with: php lazy-entity-demo.php
 */

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\DataAbstractionLayer\LazyEntityFactory;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;

require __DIR__ . '/vendor/autoload.php';

$languageId = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';
$localeId = '0195146d61dd711d9c1ee4a1f8c1cb4f';

// --- simulate what the EntityHydrator produces today for:
// (new Criteria([$languageId]))->addFields(['name', 'translationCode.code', 'locale.code'])
$partialLocale = new PartialEntity();
$partialLocale->internalSetEntityData('locale', new FieldVisibility([]));
$partialLocale->setUniqueIdentifier($localeId);
$partialLocale->assign(['id' => $localeId, 'code' => 'en-GB']);
$partialLocale->addTranslated('name', 'English (UK)');

$partialLanguage = new PartialEntity();
$partialLanguage->internalSetEntityData('language', new FieldVisibility([]));
$partialLanguage->setUniqueIdentifier($languageId);
$partialLanguage->assign([
    'id' => $languageId,
    'name' => 'English',
    'translationCode' => $partialLocale,
]);

// --- the prototype: convert into a lazy ghost of the REAL entity class
$language = LazyEntityFactory::fromPartial(LanguageEntity::class, $partialLanguage);

echo "1) real type hints, no PartialEntity:\n";
echo '   instanceof LanguageEntity: ' . var_export($language instanceof LanguageEntity, true) . "\n";
echo '   instanceof of nested association (LocaleEntity): ' . var_export($language->getTranslationCode() instanceof LocaleEntity, true) . "\n\n";

echo "2) direct getters instead of has()/get() pre-checks:\n";
echo '   getId():                        ' . $language->getId() . "\n";
echo '   getName():                      ' . $language->getName() . "\n";
echo '   getTranslationCode()->getCode(): ' . $language->getTranslationCode()?->getCode() . "\n";
echo '   getUniqueIdentifier():          ' . $language->getUniqueIdentifier() . "\n";
echo '   nested getTranslation(name):    ' . $language->getTranslationCode()?->getTranslation('name') . "\n\n";

echo "3) accessing a NOT loaded field via dedicated getter throws a meaningful exception:\n";
try {
    $language->getParentId();
} catch (DataAbstractionLayerException $e) {
    echo '   [' . $e->getErrorCode() . '] ' . $e->getMessage() . "\n\n";
}

echo "4) same for the generic get() accessor:\n";
try {
    $language->get('localeId');
} catch (DataAbstractionLayerException $e) {
    echo '   [' . $e->getErrorCode() . '] ' . $e->getMessage() . "\n\n";
}

echo "5) and for fields of nested lazy associations:\n";
try {
    $language->getTranslationCode()?->getTerritory();
} catch (DataAbstractionLayerException $e) {
    echo '   [' . $e->getErrorCode() . '] ' . $e->getMessage() . "\n";
}
