<?php declare(strict_types=1);

namespace Shopware\Api\User\Collection;

use Shopware\Api\Locale\Collection\LocaleBasicCollection;
use Shopware\Api\Media\Collection\MediaBasicCollection;
use Shopware\Api\User\Struct\UserDetailStruct;

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

    public function getMediaUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getMedia()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
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
