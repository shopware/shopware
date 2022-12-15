/**
 * @package admin
 */

import template from './sw-app-actions.html.twig';
import './sw-app-actions.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { hasOwnProperty } = Shopware.Utils.object;

const actionTypeConstants = Object.freeze({
    ACTION_SHOW_NOTIFICATION: 'notification',
    ACTION_RELOAD_DATA: 'reload',
    ACTION_OPEN_NEW_TAB: 'openNewTab',
    ACTION_OPEN_MODAL: 'openModal',
});

const modalSizeMapping = {
    small: 'small',
    medium: 'default',
    large: 'large',
    fullscreen: 'full',
};

const IFRAME_KEY = 'app.action_button.iframe';

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
Component.register('sw-app-actions', {
    template,

    extensionApiDevtoolInformation: {
        property: 'ui.actionButton',
        entity: (currentComponent) => `${currentComponent.entity}`,
        view: (currentComponent) => `${currentComponent.view}`,
    },

    inject: [
        'feature',
        'appActionButtonService',
        'repositoryFactory',
        'extensionSdkService',
    ],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            actions: [],
            matchedRoutes: [],
            isOpenModal: false,
            isOpenConfirmModal: false,
            title: '',
            action: null,
            size: 'default',
            isExpanded: false,
            iframeUrl: '',
            isShowModalConfirm: true,
            iframeUserConfig: null,
        };
    },

    computed: {
        entity() {
            return this.$route?.meta?.$module?.entity;
        },

        view() {
            const matchedRoute = this.matchedRoutes.filter((match) => {
                return !!match?.meta?.appSystem?.view;
            }).pop();

            return matchedRoute?.meta?.appSystem?.view;
        },

        areActionsAvailable() {
            return !!this.actions
                && this.actions.length > 0
                && this.params.length > 0;
        },

        params() {
            return Shopware.State.get('shopwareApps').selectedIds;
        },

        userConfigRepository() {
            return this.repositoryFactory.create('user_config');
        },

        currentUser() {
            return Shopware.State.get('session').currentUser;
        },

        userConfigCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.equals('key', IFRAME_KEY));
            criteria.addFilter(Criteria.equals('userId', this.currentUser?.id));

            return criteria;
        },

        extensionSdkButtons() {
            return Shopware.State.get('actionButtons').buttons.filter((button) => {
                return button.entity === this.entity && button.view === this.view;
            });
        },
    },

    watch: {
        $route: {
            immediate: true,
            handler() {
                this.matchedRoutes = this.$router.currentRoute.matched;
                this.loadActions();
            },
        },

        extensionSdkButtons() {
            // If the matching entity and view is already open and the iframe call comes in late reload
            this.loadActions();
        },
    },

    methods: {
        async runAction(action) {
            const entityIdList = { ids: this.params };

            if (hasOwnProperty(action, 'callback') && typeof action.callback === 'function') {
                action.callback(action.entity, entityIdList.ids);

                return;
            }

            const { data } = await this.appActionButtonService.runAction(action.id, entityIdList);
            const { actionType, redirectUrl, status, message } = data;

            this.action = this.actions.find(actionsAction => {
                return actionsAction.id === action.id;
            });

            switch (actionType) {
                case actionTypeConstants.ACTION_OPEN_NEW_TAB:
                    window.open(redirectUrl, '_blank');
                    break;
                case actionTypeConstants.ACTION_SHOW_NOTIFICATION:
                    this.createNotification({
                        variant: status,
                        message: message,
                    });
                    break;
                case actionTypeConstants.ACTION_RELOAD_DATA:
                    window.location.reload();
                    break;
                case actionTypeConstants.ACTION_OPEN_MODAL:
                    await this.getUserConfig();
                    this.size = modalSizeMapping[data.size] || 'default';
                    this.iframeUrl = data.iframeUrl;
                    this.isExpanded = data.expand === true;
                    this.isOpenModal = true;

                    break;
                default:
                    break;
            }
        },

        async loadActions() {
            try {
                this.actions = await this.appActionButtonService.getActionButtonsPerView(this.entity, this.view);
                this.actions.push(...this.extensionSdkButtons);
            } catch (e) {
                this.actions = [];

                // ignore missing parameter exception for pages without correct view
                if (!!e.name && e.name === 'InvalidActionButtonParameterError') {
                    return;
                }

                this.createNotificationError({
                    message: this.$tc('sw-app.component.sw-app-actions.messageErrorFetchButtons'),
                });
            }
        },

        onCloseModal() {
            if (this.size === modalSizeMapping.small && !this.isExpanded) {
                this.isOpenModal = false;
            } else {
                this.onOpenModalConfirm();
            }
        },

        onOpenModalConfirm() {
            if (this.iframeUserConfig.value.isShowModalConfirm) {
                this.isOpenConfirmModal = true;
                return;
            }

            this.isOpenModal = false;
        },

        onCloseModalConfirm() {
            this.isOpenConfirmModal = false;
        },

        async onConfirmClose() {
            this.saveConfig(this.isShowModalConfirm);

            await this.onCloseModalConfirm();
            this.isOpenModal = false;
        },

        onChangeCheckboxShow() {
            this.isShowModalConfirm = !this.isShowModalConfirm;
        },

        getUserConfig() {
            this.userConfigRepository.search(this.userConfigCriteria, Shopware.Context.api).then(response => {
                if (response.length) {
                    this.iframeUserConfig = response.first();
                } else {
                    this.iframeUserConfig = this.userConfigRepository.create(Shopware.Context.api);
                    this.iframeUserConfig.key = IFRAME_KEY;
                    this.iframeUserConfig.userId = this.currentUser?.id;
                    this.iframeUserConfig.value = {
                        isShowModalConfirm: true,
                    };
                }
            });
        },

        saveConfig(value) {
            this.iframeUserConfig.value = {
                isShowModalConfirm: value,
            };

            this.userConfigRepository.save(this.iframeUserConfig, Shopware.Context.api).then(() => {
                this.getUserConfig();
            });
        },
    },
});
