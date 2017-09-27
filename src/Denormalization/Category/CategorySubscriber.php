<?php

namespace Shopware\Denormalization\Category;

use Shopware\Category\Event\CategoryWrittenEvent;
use Shopware\Category\Repository\CategoryRepository;
use Shopware\Context\Struct\TranslationContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CategorySubscriber implements EventSubscriberInterface
{
    /**
     * @var CategoryPathBuilder
     */
    private $pathBuilder;

    /**
     * @var CategoryRepository
     */
    private $repository;

    public function __construct(
        CategoryPathBuilder $pathBuilder,
        CategoryRepository $repository
    ) {
        $this->pathBuilder = $pathBuilder;
        $this->repository = $repository;
    }

    public static function getSubscribedEvents()
    {
        return [
            CategoryWrittenEvent::NAME => 'updatePath',
        ];
    }

    public function updatePath(CategoryWrittenEvent $event)
    {
        $context = new TranslationContext('SWAG-SHOP-UUID-1', true, null);
        $categories = $this->repository->read($event->getCategoryUuids(), $context);
        foreach ($categories->getParentUuids() as $uuid) {
            $this->pathBuilder->update($uuid, $context);
        }
    }
}
