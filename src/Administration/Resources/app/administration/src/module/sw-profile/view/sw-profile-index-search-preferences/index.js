import template from './sw-profile-index-search-preferences.html.twig';
import './sw-profile-index-search-preferences.scss';

const { Component, Module, State, Mixin } = Shopware;
const { mapState } = Shopware.Component.getComponentHelper();

Component.register('sw-profile-index-search-preferences', {
    template,

    inject: ['repositoryFactory', 'acl', 'searchPreferencesService'],

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

        defaultSearchPreferences() {
            return this.searchPreferencesService.getDefaultSearchPreferences();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.isLoading = true;

            try {
                const userSearchPreferences = await this.searchPreferencesService.getUserSearchPreferences();

                if (!userSearchPreferences) {
                    this.createUserSearchPreferences();

                    const searchPreferences = this.searchPreferencesService.processSearchPreferences(
                        this.defaultSearchPreferences,
                    );
                    State.commit('swProfile/setSearchPreferences', searchPreferences);

                    return;
                }

                State.commit('swProfile/setUserSearchPreferences', userSearchPreferences);

                const tempSearchPreferences = this.defaultSearchPreferences.reduce((accumulator, currentValue) => {
                    const value = userSearchPreferences.find((item) => {
                        return Object.keys(item)[0] === Object.keys(currentValue)[0];
                    });

                    accumulator.push(value || currentValue);

                    return accumulator;
                }, []);

                const searchPreferences = this.searchPreferencesService.processSearchPreferences(tempSearchPreferences);
                State.commit('swProfile/setSearchPreferences', searchPreferences);
            } catch (error) {
                this.createNotificationError({ message: error.message });
                State.commit('swProfile/setSearchPreferences', []);
                State.commit('swProfile/setUserSearchPreferences', {});
            } finally {
                this.isLoading = false;
            }
        },

        createUserSearchPreferences() {
            const userSearchPreferences = this.searchPreferencesService.createUserSearchPreferences();

            State.commit('swProfile/setUserSearchPreferences', userSearchPreferences);
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
            const searchPreferences = this.searchPreferencesService.processSearchPreferences(this.defaultSearchPreferences);
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
