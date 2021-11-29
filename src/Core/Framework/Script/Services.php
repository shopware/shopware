<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script;

use Shopware\Core\Checkout\Cart\Facade\CartFacade;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade;
use Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade;

/**
 * This class is intended for auto completion in twig templates. So the developer can
 * set a doc block to get auto completion for all services.
 *
 * @example: {# @var services \Shopware\Core\Framework\Script\Services #}
 *
 * @method CartFacade                   cart()
 * @method RepositoryFacade             repository()
 * @method SystemConfigFacade           config()
 * @method SalesChannelRepositoryFacade store()
 */
abstract class Services
{
}
