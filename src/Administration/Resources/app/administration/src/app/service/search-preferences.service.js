import { KEY_USER_SEARCH_PREFERENCE } from 'src/app/service/search-ranking.service';

/**
* @description Exposes an user search preferences
* @constructor
* @param {Object} Object.userConfigRepository
*/
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function SearchPreferencesService({ userConfigRepository: _userConfigRepository }) {
    return {
        getDefaultSearchPreferences,
        getUserSearchPreferences,
        createUserSearchPreferences,
        processSearchPreferences,
        processSearchPreferencesFields,
    };

    /**
    * @description Get default search preferences
    * @returns {Array}
    */
    function getDefaultSearchPreferences() {
        const defaultSearchPreferences = [];

        Shopware.Module.getModuleRegistry().forEach(({ manifest }) => {
            if (
                manifest.entity &&
                Shopware.Service('acl').can(`${manifest.entity}.editor`) &&
                manifest.defaultSearchConfiguration
            ) {
                defaultSearchPreferences.push({
                    [manifest.entity]: manifest.defaultSearchConfiguration,
                });
            }
        });

        return defaultSearchPreferences;
    }

    /**
    * @description Get user search preferences
    * @returns {Promise}
    */
    function getUserSearchPreferences() {
        return new Promise((resolve) => {
            Shopware.Service('userConfigService').search([KEY_USER_SEARCH_PREFERENCE]).then((response) => {
                resolve(response.data[KEY_USER_SEARCH_PREFERENCE] || null);
            });
        });
    }

    /**
    * @description Define user search preferences
    * @returns {Object}
    */
    function createUserSearchPreferences() {
        const userSearchPreferences = _userConfigRepository.create();

        _getUserConfigCriteria().filters.forEach(({ field, value }) => {
            userSearchPreferences[field] = value;
        });

        return userSearchPreferences;
    }

    /**
    * @description Process search preferences
    * @param {Array} tempSearchPreferences
    * [{
    *     customer: {
    *         _searchable: false,
    *         company: {
    *             _searchable: false,
    *             _score: 500,
    *         },
    *         defaultBillingAddress: {
    *             company: {
    *                 _searchable: false,
    *                 _score: 500,
    *             }
    *         },
    *         defaultShippingAddress: {
    *             company: {
    *                 _searchable: false,
    *                 _score: 500,
    *             }
    *         }
    *     }
    * }]
    * @returns {Array}
    * [{
    *     entityName: 'customer'
    *     _searchable: false,
    *     fields: [{
    *         fieldName: 'company',
    *         _score: 500,
    *         _searchable: false
    *     }, {
    *         fieldName: 'defaultBillingAddress.company',
    *         _score: 500,
    *         _searchable: false
    *     }, {
    *         fieldName: 'defaultShippingAddress.company',
    *         _score: 500,
    *         _searchable: false
    *     }]
    * }]
    */
    function processSearchPreferences(tempSearchPreferences) {
        const searchPreferences = [];

        tempSearchPreferences = Object.assign({}, ...tempSearchPreferences);
        Object.entries(tempSearchPreferences).forEach(([entityName, { _searchable, ...rest }]) => {
            const fields = _getFields(rest);
            searchPreferences.push({ entityName, _searchable, fields });
        });
        searchPreferences.sort((a, b) => b.fields.length - a.fields.length);

        return searchPreferences;
    }

    /**
    * @description Process search preferences fields
    * @param {Array} tempSearchPreferencesFields
    * [{
    *     fieldName: 'company',
    *     _searchable: true,
    *     _score: 500,
    *     group: [{
    *             fieldName: 'company',
    *             _score: 500,
    *             _searchable: true
    *         },
    *         {
    *             fieldName: 'defaultBillingAddress.company',
    *             _score: 500,
    *             _searchable: true
    *         },
    *         {
    *             fieldName: 'defaultShippingAddress.company',
    *             _score: 500,
    *             _searchable: true
    *         }
    *     ]
    * }]
    * @returns {Object}
    * {
    *     company: {
    *         _score: 500,
    *         _searchable: true
    *     }
    *     defaultBillingAddress: {
    *         company: {
    *             _score: 500,
    *             _searchable: true
    *         }
    *     }
    *     defaultShippingAddress: {
    *         company: {
    *             _score: 500,
    *             _searchable: true
    *         }
    *     }
    * }
    */
    function processSearchPreferencesFields(tempSearchPreferencesFields) {
        let searchPreferencesFields = {};

        tempSearchPreferencesFields.forEach((field) => {
            field.group.forEach((group) => {
                const searchPreferencesField = Shopware.Utils.object.set({}, group.fieldName, {
                    _searchable: field._searchable,
                    _score: field._score,
                });
                searchPreferencesFields = Shopware.Utils.object.deepMergeObject(
                    searchPreferencesFields,
                    searchPreferencesField,
                );
            });
        });

        return searchPreferencesFields;
    }

    /**
     * @private
     */
    function _getUserConfigCriteria() {
        const criteria = new Shopware.Data.Criteria();

        criteria.addFilter(Shopware.Data.Criteria.equals('key', KEY_USER_SEARCH_PREFERENCE));
        criteria.addFilter(Shopware.Data.Criteria.equals('userId', _getCurrentUser()?.id));

        return criteria;
    }

    /**
     * @private
     */
    function _getCurrentUser() {
        return Shopware.State.get('session').currentUser;
    }

    /**
     * @private
     */
    function _getFields(data) {
        const fieldsGroup = {};

        Object.entries(data).forEach(([key, value]) => {
            const fields = _flattenFields(value, `${key}.`);
            _groupFields(fields, fieldsGroup);
        });

        return Object.values(fieldsGroup);
    }

    /**
     * @private
     */
    function _flattenFields(fields, prefix = '') {
        return Object.keys(fields).reduce((accumulator, currentValue) => {
            if (typeof fields[currentValue] === 'object') {
                return [...accumulator, ..._flattenFields(fields[currentValue], `${prefix + currentValue}.`)];
            }

            if (typeof fields[currentValue] === 'number') {
                return accumulator;
            }

            const fieldName = prefix.substring(0, prefix.length - 1);
            return [...accumulator, { fieldName, ...fields }];
        }, []);
    }

    /**
     * @private
     */
    function _groupFields(fields, fieldsGroup) {
        [...fields].forEach((item) => {
            let lastFieldName = item.fieldName.slice(item.fieldName.lastIndexOf('.') + 1);
            if (item.fieldName.includes('tags.name')) {
                lastFieldName = 'tagsName';
            }
            if (item.fieldName.includes('country.name')) {
                lastFieldName = 'countryName';
            }
            if (item.fieldName.includes('mediaFolder.name')) {
                lastFieldName = 'mediaFolderName';
            }
            if (item.fieldName.includes('payload.code')) {
                lastFieldName = 'promotionCode';
            }

            fieldsGroup[lastFieldName] ??= {
                group: [],
                fieldName: lastFieldName,
                _searchable: item._searchable,
                _score: item._score,
            };

            fieldsGroup[lastFieldName].group.push(item);
        });
    }
}
