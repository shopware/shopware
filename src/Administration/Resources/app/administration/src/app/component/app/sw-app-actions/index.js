/**
 * @sw-package framework
 */

import template from './sw-app-actions.html.twig';
import './sw-app-actions.scss';

const { Mixin } = Shopware;
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

function getAppSystemView(matchedRoutes) {
    const matchedRoute = (Array.isArray(matchedRoutes) ? matchedRoutes : [])
        .filter((match) => {
            return !!match?.meta?.appSystem?.view;
        })
        .pop();

    return matchedRoute?.meta?.appSystem?.view;
}

/**
 * @private
 */
export default {
    template,

    extensionApiDevtoolInformation: {
        property: 'ui.actionButton',
        entity: (currentComponent) => `${currentComponent.entity}`,
        view: (currentComponent) => `${currentComponent.view}`,
    },

    inject: [
        'feature',
        'appActionButtonService',
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
            return getAppSystemView(this.matchedRoutes);
        },

        areActionsAvailable() {
            return !!this.actions && this.actions.length > 0 && this.params.length > 0;
        },

        params() {
            return Shopware.Store.get('shopwareApps').selectedIds;
        },

        extensionSdkButtons() {
            return Shopware.Store.get('actionButtons').buttons.filter((button) => {
                return button.entity === this.entity && button.view === this.view;
            });
        },
    },

    watch: {
        $route: {
            immediate: true,
            handler(route, previousRoute) {
                this.matchedRoutes = Array.isArray(route?.matched) ? route.matched : [];

                const entity = route?.meta?.$module?.entity;
                const previousEntity = previousRoute?.meta?.$module?.entity;
                const view = getAppSystemView(route?.matched);
                const previousView = getAppSystemView(previousRoute?.matched);

                // Listing routes update query parameters for pagination, filters and sorting. Action buttons only
                // depend on the entity and view, so those query-only route changes must not trigger another request.
                if (entity === previousEntity && view === previousView) {
                    return;
                }

                this.loadActions();
            },
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

            this.action = this.actions.find((actionsAction) => {
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
                    this._reloadPage();
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
                    message: this.$t('sw-app.component.sw-app-actions.messageErrorFetchButtons'),
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

        async getUserConfig() {
            this.iframeUserConfig = {
                key: IFRAME_KEY,
                value: (await Shopware.Service('userConfigService').search([IFRAME_KEY]))?.data?.[IFRAME_KEY] || {
                    isShowModalConfirm: true,
                },
            };
        },

        /** Thin wrapper so tests can spy on navigation without mocking window.location (non-configurable in JSDOM v26). */
        _reloadPage() {
            window.location.reload();
        },

        saveConfig(value) {
            this.iframeUserConfig.value = {
                isShowModalConfirm: value,
            };

            Shopware.Service('userConfigService')
                .upsert({
                    [IFRAME_KEY]: this.iframeUserConfig.value,
                })
                .then(() => {
                    this.getUserConfig();
                });
        },
    },
};
