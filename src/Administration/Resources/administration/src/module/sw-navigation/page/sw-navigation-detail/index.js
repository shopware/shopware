import { Component, Mixin, State } from 'src/core/shopware';
import { warn } from 'src/core/service/utils/debug.utils';
import type from 'src/core/service/utils/types.utils';
import template from './sw-navigation-detail.html.twig';
import './sw-navigation-detail.scss';

Component.register('sw-navigation-detail', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder')
    ],

    data() {
        return {
            navigation: null,
            navigations: [],
            isLoading: false,
            isMobileViewport: null,
            splitBreakpoint: 1024
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.navigation ? this.placeholder(this.navigation, 'name') : '';
        },

        navigationStore() {
            return State.getStore('navigation');
        },

        pageClasses() {
            return {
                'has--navigation': !!this.navigation && !this.isLoading,
                'is--mobile': !!this.isMobileViewport
            };
        }
    },

    watch: {
        '$route.params.id'() {
            this.setNavigation();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            this.checkViewport();
            this.registerListener();
        },

        registerListener() {
            this.$device.onResize({
                listener: this.checkViewport.bind(this)
            });
        },

        checkViewport() {
            this.isMobileViewport = this.$device.getViewportWidth() < this.splitBreakpoint;
        },

        setNavigation() {
            const navigationId = this.$route.params.id;

            if (!navigationId) {
                this.resetNavigation();
                return;
            }

            this.navigation = null;
            this.isLoading = true;

            this.navigationStore.getByIdAsync(navigationId).then((response) => {
                response = this.initLayoutConfig(response);
                this.navigation = response;
                this.isLoading = false;
            });
        },

        initLayoutConfig(navigation) {
            if (!type.isPlainObject(navigation.slotConfig)) {
                navigation.setLocalData({
                    slotConfig: {}
                });
            }

            return navigation;
        },

        onSaveNavigations() {
            return this.navigationStore.sync();
        },

        resetNavigation() {
            this.navigation = null;
            this.isLoading = false;
        },

        onSave() {
            const navigationName = this.navigation.name;
            const titleSaveSuccess = this.$tc('sw-navigation.general.titleSaveSuccess');
            const messageSaveSuccess = this.$tc('sw-navigation.general.messageSaveSuccess', 0, { name: navigationName });
            const titleSaveError = this.$tc('global.notification.notificationSaveErrorTitle');
            const messageSaveError = this.$tc(
                'global.notification.notificationSaveErrorMessage',
                0, { entityName: navigationName }
            );

            return this.navigation.save().then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            }).catch(exception => {
                this.createNotificationError({
                    title: titleSaveError,
                    message: messageSaveError
                });
                warn(this._name, exception.message, exception.response);
            });
        }
    }
});
