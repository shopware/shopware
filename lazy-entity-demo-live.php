<?php declare(strict_types=1);

/**
 * Prototype demo (full stack): partial loading via the real DAL repository produces
 * PHP 8.4 lazy ghost objects of the real entity classes.
 *
 * Runs the same query as BaseSalesChannelContextFactory::getLanguageInfo() against the real database.
 *
 * Run with: php lazy-entity-demo-live.php
 */

use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\System\Language\LanguageEntity;
use Symfony\Component\Dotenv\Dotenv;

$classLoader = require __DIR__ . '/vendor/autoload.php';

(new Dotenv())->bootEnv(__DIR__ . '/.env');

$kernel = KernelFactory::create('dev', false, $classLoader, new StaticKernelPluginLoader($classLoader, null));
$kernel->boot();

$container = $kernel->getContainer();

/** @var EntityRepository $languageRepository */
$languageRepository = $container->get('language.repository');

// enable the prototype behaviour
EntityHydrator::$createLazyEntities = true;

$context = Context::createDefaultContext();
$currentLanguageId = $context->getLanguageId();

// same criteria as in BaseSalesChannelContextFactory::getLanguageInfo()
$criteria = (new Criteria([$currentLanguageId]))->addFields([
    'name',
    'translationCode.code',
    'locale.code',
]);

$currentLanguage = $languageRepository->search($criteria, $context)->getEntities()->get($currentLanguageId);

echo '1) repository returns the real entity class: ' . get_debug_info($currentLanguage) . "\n";

\assert($currentLanguage instanceof LanguageEntity);

echo '2) getName():                          ' . $currentLanguage->getName() . "\n";

$locale = $currentLanguage->getTranslationCode() ?? $currentLanguage->getLocale();
echo '3) getTranslationCode()?->getCode():   ' . $locale?->getCode() . "\n";

echo "4) accessing a field that was not part of Criteria::addFields():\n";

try {
    $currentLanguage->getLocaleId();
} catch (DataAbstractionLayerException $e) {
    echo '   [' . $e->getErrorCode() . '] ' . $e->getMessage() . "\n";
}

function get_debug_info(?object $object): string
{
    return $object === null ? 'null' : $object::class;
}
