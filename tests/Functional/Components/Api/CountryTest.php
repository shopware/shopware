<?php
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

namespace Shopware\Tests\Functional\Components\Api;

use Shopware\Components\Api\Resource\Country;
use Shopware\Models\Country\State;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class CountryTest extends TestCase
{
    /**
     * @var \Shopware\Components\Api\Resource\Country
     */
    protected $resource;

    /**
     * @var array
     */
    private static $existingCountryIds = 0;

    /**
     * @var array
     */
    private static $existingStatesIds = 0;

    /**
     * Saves the IDs of currently existing countries and states.
     */
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        static::$existingCountryIds = Shopware()->Db()->fetchCol(
           'SELECT id
            FROM s_core_countries'
        );
        static::$existingStatesIds = Shopware()->Db()->fetchCol(
           'SELECT id
            FROM s_core_countries_states'
        );
    }

    /**
     * Restores the state of the 's_core_countries' and 's_core_countries_states' tables
     * by deleting all entries added by this class.
     */
    public static function tearDownAfterClass()
    {
        parent::setUpBeforeClass();

        Shopware()->Db()->query(
           'DELETE FROM s_core_countries
            WHERE id NOT IN (' . implode(',', static::$existingCountryIds) . ')'
        );
        Shopware()->Db()->query(
           'DELETE FROM s_core_countries_states
            WHERE id NOT IN (' . implode(',', static::$existingStatesIds) . ')'
        );
    }

    /**
     * @return \Shopware\Components\Api\Resource\Country
     */
    public function createResource()
    {
        return new Country();
    }

    /**
     * @return \Shopware\Models\Country\Country
     */
    public function testCreate()
    {
        $area = $this->getArea();
        $data = [
            'name' => 'Test Country',
            'iso' => 'TC',
            'iso3' => 'TCY',
            'isoName' => 'TEST COUNTRY',
            'area' => $area->getId(),
        ];

        $country = $this->resource->create($data);

        $this->assertEquals($country->getName(), $data['name']);
        $this->assertEquals($country->getIso(), $data['iso']);
        $this->assertEquals($country->getIso3(), $data['iso3']);
        $this->assertEquals($country->getIsoName(), $data['isoName']);

        $this->assertNotNull($country->getArea());
        $this->assertEquals($country->getArea()->getId(), $area->getId());

        return $country;
    }

    /**
     * @return \Shopware\Models\Country\Country
     */
    public function testCreateWithState()
    {
        $state = new State();
        $state->fromArray([
            'name' => 'Test CountryState',
            'shortCode' => 'TS',
        ]);
        Shopware()->Models()->persist($state);
        Shopware()->Models()->flush($state);

        $area = $this->getArea();
        $data = [
            'name' => 'Test Country 2',
            'iso' => 'T2',
            'iso3' => 'TC2',
            'isoName' => 'TEST COUNTRY 2',
            'area' => $area->getId(),
            'states' => [
                [
                    'id' => $state->getId(),
                    'name' => 'New CountryState Name',
                    'shortCode' => 'NSC',
                ],
            ],
        ];

        $country = $this->resource->create($data);

        $this->assertEquals($country->getName(), $data['name']);
        $this->assertEquals($country->getIso(), $data['iso']);
        $this->assertEquals($country->getIso3(), $data['iso3']);
        $this->assertEquals($country->getIsoName(), $data['isoName']);

        $this->assertNotNull($country->getArea());
        $this->assertEquals($country->getArea()->getId(), $area->getId());

        $this->assertEquals($country->getStates()->count(), 1);
        $assignedState = $country->getStates()->first();
        $this->assertEquals($assignedState->getId(), $data['states'][0]['id']);
        $this->assertEquals($assignedState->getName(), $data['states'][0]['name']);
        $this->assertEquals($assignedState->getShortCode(), $data['states'][0]['shortCode']);

        return $country;
    }

    /**
     * @depends testCreateWithState
     *
     * @param \Shopware\Models\Country\Country $country
     *
     * @return \Shopware\Models\Country\Country
     */
    public function testGetOne(\Shopware\Models\Country\Country $country)
    {
        $countryData = $this->resource->getOne($country->getId());

        $this->assertEquals($countryData['id'], $country->getId());
        $this->assertEquals($countryData['name'], $country->getName());
        $this->assertEquals($countryData['iso'], $country->getIso());
        $this->assertEquals($countryData['iso3'], $country->getIso3());
        $this->assertEquals($countryData['isoName'], $country->getIsoName());
        $this->assertEquals($countryData['areaId'], $country->getArea()->getId());

        $this->assertArrayHasKey('states', $countryData);
        $this->assertCount(1, $countryData['states']);
        $firstState = $country->getStates()->first();
        $this->assertEquals($countryData['states'][0]['id'], $firstState->getId());
        $this->assertEquals($countryData['states'][0]['name'], $firstState->getName());
        $this->assertEquals($countryData['states'][0]['shortCode'], $firstState->getShortCode());
        $this->assertEquals($countryData['states'][0]['countryId'], $country->getId());

        return $country;
    }

    /**
     * @depends testGetOne
     *
     * @param \Shopware\Models\Country\Country $country
     *
     * @return \Shopware\Models\Country\Country
     */
    public function testUpdate(\Shopware\Models\Country\Country $country)
    {
        $oldState = $country->getStates()->first();
        $state = new State();
        $state->fromArray([
            'name' => 'Test CountryState 2',
            'shortCode' => 'TS2',
        ]);
        Shopware()->Models()->persist($state);
        Shopware()->Models()->flush($state);

        $area = $this->getArea(1);
        $data = [
            'name' => 'New Country Name',
            'iso' => 'NC',
            'iso3' => 'NCN',
            'isoName' => 'NEW COUNTRY',
            'area' => $area->getId(),
            'states' => [
                [
                    'id' => $oldState->getId(),
                ],
                [
                    'id' => $state->getId(),
                    'name' => 'New CountryState 2 Name',
                    'shortCode' => 'NSC2',
                ],
            ],
        ];

        $country = $this->resource->update($country->getId(), $data);

        $this->assertEquals($country->getName(), $data['name']);
        $this->assertEquals($country->getIso(), $data['iso']);
        $this->assertEquals($country->getIso3(), $data['iso3']);
        $this->assertEquals($country->getIsoName(), $data['isoName']);

        $this->assertNotNull($country->getArea());
        $this->assertEquals($country->getArea()->getId(), $area->getId());

        $this->assertEquals($country->getStates()->count(), 2);
        $oldAssignedState = $country->getStates()->first();
        $this->assertEquals($oldAssignedState->getId(), $data['states'][0]['id']);
        $this->assertEquals($oldAssignedState->getName(), $oldState->getName());
        $this->assertEquals($oldAssignedState->getShortCode(), $oldState->getShortCode());
        $newAssignedState = $country->getStates()->last();
        $this->assertEquals($newAssignedState->getId(), $data['states'][1]['id']);
        $this->assertEquals($newAssignedState->getName(), $data['states'][1]['name']);
        $this->assertEquals($newAssignedState->getShortCode(), $data['states'][1]['shortCode']);

        return $country;
    }

    /**
     * @depends testUpdate
     *
     * @param \Shopware\Models\Country\Country $country
     *
     * @return \Shopware\Models\Country\Country
     */
    public function testGetList(\Shopware\Models\Country\Country $country)
    {
        $countryData = $this->resource->getList(0, 1000);

        $this->assertArrayHasKey('data', $countryData);
        $this->assertArrayHasKey('total', $countryData);
        $this->assertCount((2 + count(static::$existingCountryIds)), $countryData['data']);
        $this->assertEquals($countryData['total'], (2 + count(static::$existingCountryIds)));

        return $country;
    }

    /**
     * @depends testGetList
     *
     * @param \Shopware\Models\Country\Country $country
     */
    public function testDelete(\Shopware\Models\Country\Country $country)
    {
        $deletedCountry = $this->resource->delete($country->getId());

        $this->assertInstanceOf('\Shopware\Models\Country\Country', $deletedCountry);
        $this->assertNull($deletedCountry->getId());
    }

    /**
     * @param int $index
     *
     * @return \Shopware\Models\Country\Area
     */
    private function getArea($index = 0)
    {
        $areas = Shopware()->Models()->getRepository('Shopware\Models\Country\Area')->findAll();

        return $areas[$index];
    }
}
