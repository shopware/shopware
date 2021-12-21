export default class AccountPageObject {
    constructor() {
        this.elements = {
            // General components
            loader: '.sw-loader',
            modal: '.sw-modal',
            modalTitle: '.sw-modal__title',
            modalFooter: '.sw-modal__footer',
            selectSelectedItem: '.sw-select__selection',
            selectInput: '.sw-select__input',

            // Admin menu
            adminMenu: '.sw-admin-menu',

            // Smart bar
            smartBarHeader: '.smart-bar__header',
            smartBarAmount: '.sw-page__smart-bar-amount',
            smartBarBack: 'a.smart-bar__back-btn',

            // Listing components
            gridRow: '.sw-grid__row',
            gridRowInlineEdit: '.sw-grid-row__inline-edit-action',

            dataGridRow: '.sw-data-grid__row',
            dataGridColumn: '.sw-data-grid__cell',
            dataGridInlineEditSave: '.sw-data-grid__inline-edit-save',

            emptyState: '.sw-empty-state',
            contextMenu: '.sw-context-menu',
            contextMenuButton: '.sw-context-button__button',

            // Create/detail components
            primaryButton: '.sw-btn-primary',
            lightButton: '.btn-light',
            cardTitle: '.sw-card__title',

            // Notifications
            alert: '.sw-alert',
            alertClose: '.sw-alert__close',
            notification: '.sw-notifications__notification',
            accountRoot: '.account',
            accountHeadline: '.account-welcome',
            accountMenu: '.js-account-menu-dropdown',
            accountSidebar: '.account-sidebar',

            // Register - Login
            registerCard: '.register-card',
            registerForm: '.register-form',
            registerSubmit: '.register-submit',
            registerCheckbox: '.register-different-shipping input',
            loginCard: '.login-card',
            loginForm: '.login-form',
            loginSubmit: '.login-submit',

            // Address
            addressRoot: '.account-address',
            addressForm: '.account-address-form',
            addressBox: '.address-box',
            overViewBillingAddress: '.overview-billing-address',
            overViewShippingAddress: '.overview-shipping-address'
        };
    }
}
