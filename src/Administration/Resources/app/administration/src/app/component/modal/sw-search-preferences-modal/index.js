/**
 * @package admin
 */

import { KEY_USER_SEARCH_PREFERENCE } from 'src/app/service/search-ranking.service';
import template from './sw-search-preferences-modal.html.twig';
import './sw-search-preferences-modal.scss';

const { Component, Mixin, Module } = Shopware;

/**
 * @private
 */
Component.register('sw-search-preferences-modal', {
    template,

    compatConfig: Shopware.compatConfig,

    inject: [
        'searchPreferencesService',
        'searchRankingService',
        'userConfigService',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
            searchPreferences: [],
            userSearchPreferences: null,
        };
    },

    computed: {
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

        searchPreferencesColumns() {
            return [
                {
                    property: 'active',
                    label: this.$tc('global.sw-search-preferences-modal.columnActive'),
                    sortable: false,
                    width: '100px',
                    align: 'center',
                },
                {
                    property: 'moduleName',
                    label: this.$tc('global.sw-search-preferences-modal.columnModuleName'),
                    sortable: false,
                },
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    beforeUnmount() {
        this.beforeDestroyComponent();
    },

    methods: {
        createdComponent() {
            this.getDataSource();
        },

        mountedComponent() {
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
            document
                .getElementById('sw-search-preferences-modal-link')
                ?.addEventListener('click', this.onOpenSearchSettings);
        },

        removeEventListeners() {
            document
                .getElementById('sw-search-preferences-modal-link')
                ?.removeEventListener('click', this.onOpenSearchSettings);
        },

        getModuleName(entityName) {
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

        onClose() {
            this.$emit('modal-close');
        },

        onOpenSearchSettings() {
            this.$emit('modal-close');
            this.$nextTick(() => {
                this.$router.push({ name: 'sw.profile.index.searchPreferences' });
            });
        },

        onCancel() {
            this.$emit('modal-close');
        },

        onSave() {
            // eslint-disable-next-line max-len
            this.userSearchPreferences = this.userSearchPreferences ?? this.searchPreferencesService.createUserSearchPreferences();
            this.userSearchPreferences.value = this.searchPreferences.map(({ entityName, _searchable, fields }) => {
                return {
                    [entityName]: {
                        _searchable,
                        ...this.searchPreferencesService.processSearchPreferencesFields(fields),
                    },
                };
            });

            this.searchRankingService.clearCacheUserSearchConfiguration();

            this.isLoading = true;
            return this.userConfigService.upsert({ [KEY_USER_SEARCH_PREFERENCE]: this.userSearchPreferences.value })
                .then(() => {
                    this.isLoading = false;
                    this.$emit('modal-close');
                    this.$root.$emit('sw-search-preferences-modal-close');
                })
                .catch((error) => {
                    this.isLoading = false;
                    this.createNotificationError({ message: error.message });
                });
        },
    },
});
