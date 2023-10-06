<?php declare(strict_types=1);

namespace Shopware\Core\Test;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
#[Package('core')]
class FixtureLoader
{
    private readonly Connection $connection;

    private readonly EntityWriterInterface $writer;

    public function __construct(
        private readonly ContainerInterface $container
    ) {
        $this->connection = $container->get(Connection::class);
        $this->writer = $container->get(EntityWriter::class);
    }

    public function load(string $content, ?IdsCollection $ids = null): IdsCollection
    {
        if (!$ids) {
            $ids = new IdsCollection([
                'currency' => Defaults::CURRENCY,
                'api-type' => Defaults::SALES_CHANNEL_TYPE_API,
                'comparison-type' => Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON,
                'storefront-type' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
                'language' => Defaults::LANGUAGE_SYSTEM,
                'locale' => $this->getLocaleIdOfSystemLanguage(),
                'es-locale' => $this->getLocaleIdFromLocaleCode('es-ES'),
            ]);
        }

        $content = $this->replaceIds($ids, $content);
        $this->sync(\json_decode($content, true, 512, \JSON_THROW_ON_ERROR));
        $this->getContainer()->get(EntityIndexerRegistry::class)->index(false);

        return $ids;
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    private function replaceIds(IdsCollection $ids, string $content): string
    {
        return (string) \preg_replace_callback('/"{.*}"/mU', function (array $match) use ($ids) {
            $key = \str_replace(['"{', '}"'], '', (string) $match[0]);

            return '"' . $ids->create($key) . '"';
        }, $content);
    }

    /**
     * @param array<array<int, mixed>> $content
     */
    private function sync(array $content): void
    {
        $operations = [];
        foreach ($content as $entity => $data) {
            $operations[] = new SyncOperation($entity, $entity, 'upsert', $data);
        }

        $this->writer->sync($operations, WriteContext::createFromContext(Context::createDefaultContext()));
    }

    private function getLocaleIdOfSystemLanguage(): string
    {
        return $this->connection
            ->fetchOne(
                'SELECT LOWER(HEX(locale_id)) FROM language WHERE id = UNHEX(:systemLanguageId)',
                ['systemLanguageId' => Defaults::LANGUAGE_SYSTEM]
            );
    }

    private function getLocaleIdFromLocaleCode(string $code): string
    {
        return $this->connection
            ->fetchOne(
                'SELECT LOWER(HEX(id)) from locale WHERE code = :code',
                ['code' => $code]
            );
    }
}
