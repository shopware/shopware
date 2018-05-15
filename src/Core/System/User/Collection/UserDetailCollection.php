<?php declare(strict_types=1);

namespace Shopware\System\User\Collection;

use Shopware\System\Locale\Collection\LocaleBasicCollection;
use Shopware\Api\Media\Collection\MediaBasicCollection;
use Shopware\System\User\Struct\UserDetailStruct;

class UserDetailCollection extends UserBasicCollection
{
    /**
     * @var UserDetailStruct[]
     */
    protected $elements = [];

    public function getLocales(): LocaleBasicCollection
    {
        return new LocaleBasicCollection(
            $this->fmap(function (UserDetailStruct $user) {
                return $user->getLocale();
            })
        );
    }

    public function getMediaIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getMedia()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getMedia(): MediaBasicCollection
    {
        $collection = new MediaBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getMedia()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return UserDetailStruct::class;
    }
}
