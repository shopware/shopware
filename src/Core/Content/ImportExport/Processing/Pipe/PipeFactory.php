<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Pipe;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\PrimaryKeyResolver;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;

#[Package('system-settings')]
class PipeFactory extends AbstractPipeFactory
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionInstanceRegistry,
        private readonly SerializerRegistry $serializerRegistry,
        private readonly PrimaryKeyResolver $primaryKeyResolver
    ) {
    }

    public function create(ImportExportLogEntity $logEntity): AbstractPipe
    {
        $pipe = new ChainPipe([
            new EntityPipe(
                $this->definitionInstanceRegistry,
                $this->serializerRegistry,
                null,
                null,
                $this->primaryKeyResolver
            ),
            new KeyMappingPipe(),
        ]);

        return $pipe;
    }

    public function supports(ImportExportLogEntity $logEntity): bool
    {
        return true;
    }
}
