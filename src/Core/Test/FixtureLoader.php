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
        $content = \file_get_contents($file);

        $ids = new IdsCollection([
            'currency' => Defaults::CURRENCY
        ]);

        $content = \preg_replace_callback('/"{.*}"/mU', function(array $match) use ($ids) {
            $key = \str_replace(['"{', '}"'], '', $match[0]);

            return '"' . $ids->create($key) . '"';
        }, $content);

        $content = \json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        $operations = [];
        foreach ($content as $entity => $data) {
            $operations[] = new SyncOperation($entity, $entity, 'upsert', $data);
        }

        $this->writer->sync($operations, WriteContext::createFromContext(Context::createDefaultContext()));

        return $ids;
    }
}
