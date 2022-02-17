<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script;

/**
 * This class is intended for auto completion in twig templates. So the developer can
 * set a doc block to get auto completion for all services.
 *
 * @example: {# @var services \Shopware\Core\Framework\Script\ServiceStubs #}
 *
 * @method \Shopware\Core\Checkout\Cart\Facade\CartFacade                                    cart()
 * @method \Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade             repository()
 * @method \Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade                      config()
 * @method \Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade store()
 * @method \Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryWriterFacade       writer()
 * @method \Shopware\Core\Framework\Script\Api\ScriptResponseFactoryFacade                   response()
 * @method \Shopware\Core\Framework\Adapter\Cache\Script\Facade\CacheInvalidatorFacade       cache()
 */
abstract class ServiceStubs
{
}
