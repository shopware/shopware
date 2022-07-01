<?php declare(strict_types=1);

namespace Shopware\Core\Test;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Test\IdsCollection;

/**
 * @internal
 */
class FixtureLoader
{
    private EntityWriterInterface $writer;

    public function __construct(EntityWriterInterface $writer)
    {
        $this->writer = $writer;
    }

    public function load(string $file): IdsCollection
    {
        $content = (string) \file_get_contents($file);

        $ids = new IdsCollection([
            'currency' => Defaults::CURRENCY,
            'api-type' => Defaults::SALES_CHANNEL_TYPE_API,
            'comparison-type' => Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON,
            'storefront-type' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'language' => Defaults::LANGUAGE_SYSTEM,
        ]);

        $content = $this->replaceIds($ids, $content);

        $this->sync(\json_decode($content, true, 512, \JSON_THROW_ON_ERROR));

        return $ids;
    }

    private function replaceIds(IdsCollection $ids, string $content): string
    {
        return (string) \preg_replace_callback('/"{.*}"/mU', function (array $match) use ($ids) {
            $key = \str_replace(['"{', '}"'], '', $match[0]);

            return '"' . $ids->create($key) . '"';
        }, $content);
    }

    private function sync(array $content): void
    {
        $operations = [];
        foreach ($content as $entity => $data) {
            $operations[] = new SyncOperation($entity, $entity, 'upsert', $data);
        }

        $this->writer->sync($operations, WriteContext::createFromContext(Context::createDefaultContext()));
    }
}
