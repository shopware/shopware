export default class GeneralPageObject {
    constructor(browser) {
        this.browser = browser;

        this.elements = {
            adminMenu: '.sw-admin-menu',

            smartBarHeader: '.smart-bar__header',
            smartBarAmount: '.sw-page__smart-bar-amount',
            smartBarBack: 'a.smart-bar__back-btn',

            cardTitle: '.sw-card__title',

            gridRow: '.sw-grid__row',
            gridRowInlineEdit: '.sw-grid-row__inline-edit-action',
            emptyState: '.sw-empty-state',
            contextMenu: '.sw-context-menu',
            contextMenuButton: '.sw-context-button__button',

            primaryButton: '.sw-button--primary',
            dangerButton: '.sw-button--danger',
            loader: '.sw-loader',

            modal: '.sw-modal',
            modalTitle: '.sw-modal__title',
            modalFooter: '.sw-modal__footer',

            alert: '.sw-alert',
            alertClose: '.sw-alert__close'
        };
    }
}