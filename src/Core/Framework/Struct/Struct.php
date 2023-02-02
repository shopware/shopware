<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
abstract class Struct implements \JsonSerializable, ExtendableInterface
{
    //allows to clone full struct with all references
    use CloneTrait;

    //allows json_encode and to decode object via json serializer
    use JsonSerializableTrait;

    //allows to assign array data to this object
    use AssignArrayTrait;

    //allows to add values to an internal attribute storage
    use ExtendableTrait;

    //allows to create a new instance with all data of the provided object
    use CreateFromTrait;

    //allows access to all protected variables of the object
    use VariablesAccessTrait;

    public function getApiAlias(): string
    {
        $class = static::class;

        $class = explode('\\', $class);
        $class = implode('', $class);

        return ltrim(mb_strtolower((string) preg_replace('/[A-Z]/', '_$0', $class)), '_');
    }
}
