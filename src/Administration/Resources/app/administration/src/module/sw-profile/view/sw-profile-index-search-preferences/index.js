import template from './sw-profile-index-search-preferences.html.twig';
import './sw-profile-index-search-preferences.scss';

const { Component, Module, State, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState } = Shopware.Component.getComponentHelper();

Component.register('sw-profile-index-search-preferences', {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
        };
    },

    computed: {
        ...mapState('swProfile', [
            'searchPreferences',
        ]),

        userConfigRepository() {
            return this.repositoryFactory.create('user_config');
        },

        userConfigCriteria() {
            const criteria = new Criteria();

            criteria.addFilter(Criteria.equals('key', 'search.preferences'));
            criteria.addFilter(Criteria.equals('userId', this.currentUser?.id));

            return criteria;
        },

        defaultSearchPreferences() {
            const defaultSearchPreferences = [];

            Module.getModuleRegistry().forEach(({ manifest }) => {
                if (manifest.entity && manifest.defaultSearchConfiguration) {
                    defaultSearchPreferences.push({
                        [manifest.entity]: manifest.defaultSearchConfiguration,
                    });
                }
            });

            return defaultSearchPreferences;
        },

        currentUser() {
            return Shopware.State.get('session').currentUser;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.isLoading = true;

            try {
                const userSearchPreferences = await this.getUserSearchPreferences();
                if (userSearchPreferences.length > 0) {
                    const data = userSearchPreferences.first();

                    const searchPreferences = this.getEntitySearchPreferences(data.value);
                    State.commit('swProfile/setSearchPreferences', searchPreferences);
                    State.commit('swProfile/setUserSearchPreferences', data);

                    return;
                }

                this.createUserSearchPreferences();
                const searchPreferences = this.getEntitySearchPreferences(this.defaultSearchPreferences);
                State.commit('swProfile/setSearchPreferences', searchPreferences);
            } catch (error) {
                this.createNotificationError({ message: error.message });
                State.commit('swProfile/setSearchPreferences', []);
                State.commit('swProfile/setUserSearchPreferences', {});
            } finally {
                this.isLoading = false;
            }
        },

        getUserSearchPreferences() {
            return this.userConfigRepository.search(this.userConfigCriteria);
        },

        createUserSearchPreferences() {
            const userSearchPreferences = this.userConfigRepository.create();

            this.userConfigCriteria.filters.forEach(({ field, value }) => {
                userSearchPreferences[field] = value;
            });

            State.commit('swProfile/setUserSearchPreferences', userSearchPreferences);
        },

        /**
        * @description Get search preferences from all entities
        * @param {array} entities
        * [
        *    ...
        *    {
        *        customer: {
        *            _searchable: false,
        *            company: {
        *                _searchable: false,
        *                _score: 500,
        *            },
        *            defaultBillingAddress: {
        *                company: {
        *                    _searchable: false,
        *                    _score: 500,
        *                }
        *            },
        *            defaultShippingAddress: {
        *                company: {
        *                    _searchable: false,
        *                    _score: 500,
        *                }
        *            }
        *        }
        *    }
        *    ...
        * ]
        * @returns {array} A transformation from entities
        * [
        *    ...
        *    {
        *        entityName: 'customer'
        *        _searchable: false,
        *        fields: [
        *            {
        *                fieldName: 'company',
        *                _score: 500,
        *                _searchable: false
        *            }, {
        *                fieldName: 'defaultBillingAddress.company',
        *                _score: 500,
        *                _searchable: false
        *            }, {
        *                fieldName: 'defaultShippingAddress.company',
        *                _score: 500,
        *                _searchable: false
        *            }
        *        ]
        *    }
        *    ...
        * ]
        */
        getEntitySearchPreferences(entities) {
            const searchPreferences = [];

            entities = Object.assign({}, ...entities);
            Object.entries(entities).forEach(([entityName, { _searchable, ...rest }]) => {
                const fields = this.getFields(rest);
                searchPreferences.push({ entityName, _searchable, fields });
            });
            searchPreferences.sort((a, b) => b.fields.length - a.fields.length);

            return searchPreferences;
        },

        getFields(data) {
            const fieldsGroup = {};

            Object.entries(data).forEach(([key, value]) => {
                const fields = this.flattenFields(value, `${key}.`);
                this.groupFields(fields, fieldsGroup);
            });

            return Object.values(fieldsGroup);
        },

        flattenFields(fields, prefix = '') {
            return Object.keys(fields).reduce((accumulator, currentValue) => {
                if (typeof fields[currentValue] === 'object') {
                    return [...accumulator, ...this.flattenFields(fields[currentValue], `${prefix + currentValue}.`)];
                }

                if (typeof fields[currentValue] === 'number') {
                    return accumulator;
                }

                const fieldName = prefix.substring(0, prefix.length - 1);
                return [...accumulator, { fieldName, ...fields }];
            }, []);
        },

        groupFields(fields, fieldsGroup) {
            [...fields].forEach((item) => {
                let lastFieldName = item.fieldName.slice(item.fieldName.lastIndexOf('.') + 1);
                if (item.fieldName.includes('tags.name')) {
                    lastFieldName = 'tagsName';
                }
                fieldsGroup[lastFieldName] ??= {
                    group: [],
                    fieldName: lastFieldName,
                    _searchable: item._searchable,
                    _score: item._score,
                };

                fieldsGroup[lastFieldName].group.push(item);
            });
        },

        getModuleTitle(entityName) {
            const module = Module.getModuleByEntityName(entityName);

            return this.$tc(module?.manifest.title);
        },

        onSelect(event) {
            this.searchPreferences.forEach((searchPreference) => {
                if (!this.acl.can(`${searchPreference.entityName}.editor`)) {
                    return;
                }

                searchPreference._searchable = event;
                searchPreference.fields.forEach((field) => {
                    field._searchable = event;
                });
            });
        },

        onReset() {
            const searchPreferences = this.getEntitySearchPreferences(this.defaultSearchPreferences);
            const toReset = searchPreferences.filter((searchPreference) => {
                return this.acl.can(`${searchPreference.entityName}.editor`);
            });

            this.searchPreferences.forEach((searchPreference, index) => {
                toReset.forEach((item) => {
                    if (item.entityName === searchPreference.entityName) {
                        this.resetSearchPreference(item, this.searchPreferences[index]);
                    }
                });
            });
        },

        resetSearchPreference(toReset, searchPreference) {
            searchPreference._searchable = toReset._searchable;
            searchPreference.fields = searchPreference.fields.map((field) => {
                return toReset.fields.find((item) => item.fieldName === field.fieldName) || field;
            });
        },
    },
});
