<?php
declare(strict_types=1);
/**
 * Shopware\Core 5
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
 * "Shopware\Core" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Core\Framework\Translation;

use Doctrine\DBAL\Connection;
use Symfony\Component\Translation\Exception\InvalidResourceException;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogue;

class DatabaseLoader implements LoaderInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Loads a locale.
     *
     * @param mixed  $resource A resource
     * @param string $locale   A locale
     * @param string $domain   The domain
     *
     * @throws NotFoundResourceException when the resource cannot be found
     * @throws InvalidResourceException  when the resource cannot be loaded
     *
     * @return MessageCatalogue A MessageCatalogue instance
     */
    public function load($resource, $locale, $domain = 'messages'): MessageCatalogue
    {
        $builder = $this->connection->createQueryBuilder();

        //todo@dr no tenant id
        $snippets = $builder->select(['snippet.namespace', 'snippet.name', 'snippet.value'])
                ->from('snippet', 'snippet')
                ->where('snippet.locale = :locale')
                ->setParameter('locale', $locale)
                ->execute()
                ->fetchAll(\PDO::FETCH_GROUP);

        $catalogue = new MessageCatalogue($locale);

        foreach ($snippets as $namespace => $snippet) {
            foreach ($snippet as $item) {
                $catalogue->set($item['name'], $item['value'], $namespace);
            }
        }

        return $catalogue;
    }
}
