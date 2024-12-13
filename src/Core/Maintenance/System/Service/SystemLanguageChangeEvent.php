<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\Service;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * The system default language is always using the same id from Defaults::LANGUAGE_SYSTEM.
 * The default is changed by swapping row values in the language table.
 */
#[Package('core')]
readonly class SystemLanguageChangeEvent
{
    /**
     * @param string $previousLanguageId The id of the new default language before it was made the default language.
     *                                   Since the rows are swapped, this is now the new id of the previous default language.
     */
    public function __construct(
        public string $previousLanguageId
    ) {
    }
}
