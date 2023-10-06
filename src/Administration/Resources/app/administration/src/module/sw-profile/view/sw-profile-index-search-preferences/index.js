/**
 * @package system-settings
 */
import template from './sw-profile-index-search-preferences.html.twig';
import './sw-profile-index-search-preferences.scss';

const { Module, State, Mixin } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['searchPreferencesService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
        };
    },

    computed: {
        searchPreferences: {
            get() {
                return State.get('swProfile').searchPreferences;
            },
            set(searchPreferences) {
                State.commit('swProfile/setSearchPreferences', searchPreferences);
            },
        },

        userSearchPreferences: {
            get() {
                return State.get('swProfile').userSearchPreferences;
            },
            set(userSearchPreferences) {
                State.commit('swProfile/setUserSearchPreferences', userSearchPreferences);
            },
        },

        defaultSearchPreferences() {
            const defaultSearchPreferences = this.searchPreferencesService.getDefaultSearchPreferences();

            if (this.userSearchPreferences === null) {
                return defaultSearchPreferences;
            }

            return defaultSearchPreferences.reduce((accumulator, currentValue) => {
                const value = this.userSearchPreferences.find((item) => {
                    return Object.keys(item)[0] === Object.keys(currentValue)[0];
                });

                accumulator.push(value || currentValue);

                return accumulator;
            }, []);
        },

        adminEsEnable() {
            return Shopware.Context.app.adminEsEnable ?? false;
        },
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy() {
        this.beforeDestroyComponent();
    },

    methods: {
        async createdComponent() {
            await this.getDataSource();
            this.updateDataSource();
            this.addEventListeners();
        },

        beforeDestroyComponent() {
            this.removeEventListeners();
        },

        async getDataSource() {
            this.isLoading = true;

            try {
                this.userSearchPreferences = await this.searchPreferencesService.getUserSearchPreferences();
                this.searchPreferences = this.searchPreferencesService.processSearchPreferences(
                    this.defaultSearchPreferences,
                );
            } catch (error) {
                this.createNotificationError({ message: error.message });
                this.searchPreferences = [];
                this.userSearchPreferences = null;
            } finally {
                this.isLoading = false;
            }
        },

        addEventListeners() {
            this.$root.$on('sw-search-preferences-modal-close', this.getDataSource);
        },

        removeEventListeners() {
            this.$root.$off('sw-search-preferences-modal-close', this.getDataSource);
        },

        updateDataSource() {
            if (!this.adminEsEnable) {
                return;
            }

            this.searchPreferences.forEach((searchPreference) => {
                searchPreference.fields.forEach((field) => {
                    field._searchable = true;
                });
            });
        },

        getModuleTitle(entityName) {
            const module = Module.getModuleByEntityName(entityName);

            return this.$tc(module?.manifest.title);
        },

        onChangeSearchPreference(searchPreference) {
            if (searchPreference._searchable && searchPreference.fields.every((field) => !field._searchable)) {
                searchPreference.fields.forEach((field) => {
                    field._searchable = true;
                });
            }
        },

        onSelect(event) {
            this.searchPreferences.forEach((searchPreference) => {
                searchPreference._searchable = event;

                if (!this.adminEsEnable) {
                    searchPreference.fields.forEach((field) => {
                        field._searchable = event;
                    });
                }
            });
        },

        onReset() {
            const defaultSearchPreferences = this.searchPreferencesService.getDefaultSearchPreferences();
            const toReset = this.searchPreferencesService.processSearchPreferences(defaultSearchPreferences);

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

            if (!this.adminEsEnable) {
                searchPreference.fields = searchPreference.fields.map((field) => {
                    return toReset.fields.find((item) => item.fieldName === field.fieldName) || field;
                });
            }
        },
    },
};
