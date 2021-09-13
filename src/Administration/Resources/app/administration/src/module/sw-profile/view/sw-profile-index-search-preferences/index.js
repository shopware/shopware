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

    watch: {
        searchPreferences: {
            deep: true,
            immediate: true,
            handler(newSearchPreferences, oldSearchPreferences) {
                if (!oldSearchPreferences || newSearchPreferences.length <= 0) {
                    return;
                }

                this.updateSearchPreferences(newSearchPreferences);
            },
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
        *        _ghostValue: false,
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
            Object.entries(entities).forEach(([entityName, entitySearchPreferences]) => {
                const fieldsGroup = {};
                Object.entries(entitySearchPreferences).forEach(([key, value]) => {
                    const fields = this.flattenFields(value, `${key}.`);
                    this.groupFields(fields, fieldsGroup);
                });

                const fields = Object.values(fieldsGroup);
                searchPreferences.push({ entityName, fields });
            });
            searchPreferences.sort((a, b) => b.fields.length - a.fields.length);

            return searchPreferences;
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

        updateSearchPreferences(data) {
            data.forEach((item, index) => {
                data[index]._ghostValue = item.fields.some((field) => {
                    return field._searchable === true;
                });
                data[index]._searchable = item.fields.every((field) => {
                    return field._searchable === true;
                });
            });
        },

        getModuleTitle(entityName) {
            return this.$tc(Module.getModuleByEntityName(entityName)?.manifest.title);
        },

        onChangeEntity(event, data) {
            data.fields.forEach((field) => {
                field._searchable = event;
            });
        },

        onSelect(value) {
            this.searchPreferences.forEach((item) => {
                if (!this.acl.can(`${item.entityName}.editor`)) {
                    return;
                }

                item._searchable = value;
                item.fields.forEach((field) => {
                    field._searchable = value;
                });
            });
        },

        onReset() {
            const searchPreferences = this.getEntitySearchPreferences(this.defaultSearchPreferences);
            const editableEntities = searchPreferences.filter(({ entityName }) => {
                return this.acl.can(`${entityName}.editor`);
            });

            this.searchPreferences.forEach((searchPreference, index) => {
                editableEntities.forEach((entity) => {
                    if (entity.entityName === searchPreference.entityName) {
                        this.updateSearchPreferencesFields(entity, index);
                    }
                });
            });
        },

        updateSearchPreferencesFields(entity, index) {
            this.searchPreferences[index].fields = this.searchPreferences[index].fields.map((field) => {
                const newField = entity.fields.find((item) => item.fieldName === field.fieldName);

                return newField || field;
            });
        },
    },
});
