<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Pipe;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;

class PipeFactory extends AbstractPipeFactory
{
    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionInstanceRegistry;

    /**
     * @var SerializerRegistry
     */
    private $serializerRegistry;

    public function __construct(DefinitionInstanceRegistry $definitionInstanceRegistry, SerializerRegistry $serializerRegistry)
    {
        $this->definitionInstanceRegistry = $definitionInstanceRegistry;
        $this->serializerRegistry = $serializerRegistry;
    }

    public function create(ImportExportLogEntity $logEntity): AbstractPipe
    {
        $pipe = new ChainPipe([
            new EntityPipe($this->definitionInstanceRegistry, $this->serializerRegistry),
            new KeyMappingPipe(),
        ]);

        return $pipe;
    }

    public function supports(ImportExportLogEntity $logEntity): bool
    {
        return true;
    }
}
