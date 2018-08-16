<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Core\Framework\Migration;

use Symfony\Component\DependencyInjection\Container;

interface MigrationStepInterface
{
    /**
     * get identifier
     */
    public static function getIdentifier(): string;

    /**
     * get creation time stamp
     */
    public function getCreationTimeStamp(): int;

    /**
     * Migrations that need to be migrated before the current migration migrates
     * Return empty array if no required migrations exist
     *
     * @return string[] Array of migration identifiers
     */
    public function getRequiredMigrations(): array;

    /**
     * update non-destructive changes
     */
    public function update(Container $container);

    /**
     * update destructive changes
     */
    public function updateDestructive(Container $container);
}
