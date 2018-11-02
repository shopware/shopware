<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Flag;

/**
 * Defines that the data of the field will be loaded deferred by an event subscriber or other class.
 * Used in entity extensions for plugins or not directly fetchable associations.
 */
class Deferred extends Flag
{
}
