<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Flag;

/**
 * The value is computed by indexer or external systems and
 * cannot be written using the DAL.
 */
class Computed extends Flag
{
}
