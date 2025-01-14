<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\Core\Application\MediaUrlLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1739348080 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1739348080;
    }

    public function update(Connection $connection): void
    {
        //Work in progress
        //TODO Finish migration

        $urls = $mediaUrlGenerator->generate($paths);

        foreach ($mediaEntities as $key => $mediaEntity) {
            $connection->update(
                'media',
                ['path' => $urls[$key]],
                ['id' => $mediaEntity->getUniqueIdentifier()]
            );

            if ($mediaEntity->has('thumbnails')) {
                foreach ($mediaEntity->get('thumbnails') as $thumbnail) {
                    $connection->update(
                        'media_thumbnail',
                        ['path' => $urls[$key]],
                        ['id' => $thumbnail->getUniqueIdentifier()]
                    );
                }
            }
        }
    }

}
