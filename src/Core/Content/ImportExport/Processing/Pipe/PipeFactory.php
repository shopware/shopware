<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Pipe;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\PrimaryKeyResolver;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;

class PipeFactory extends AbstractPipeFactory
{
    private DefinitionInstanceRegistry $definitionInstanceRegistry;

    private SerializerRegistry $serializerRegistry;

    private PrimaryKeyResolver $primaryKeyResolver;

    public function __construct(
        DefinitionInstanceRegistry $definitionInstanceRegistry,
        SerializerRegistry $serializerRegistry,
        PrimaryKeyResolver $primaryKeyResolver
    ) {
        $this->definitionInstanceRegistry = $definitionInstanceRegistry;
        $this->serializerRegistry = $serializerRegistry;
        $this->primaryKeyResolver = $primaryKeyResolver;
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
