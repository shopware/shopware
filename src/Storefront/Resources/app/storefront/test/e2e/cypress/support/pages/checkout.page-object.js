export default class CheckoutPageObject {
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

            // General cart selectors
            cartItem: '.cart-item',
            cartItemFeatureList: '.product-feature-list-list',
            cartItemFeatureListItem: '.product-feature-list-item',
            cartItemFeatureContainer: '.product-feature-feature',
            cartItemFeatureLabel: '.product-feature-label',
            cartItemFeatureValue: '.product-feature-value',

            // Cart widget
            cardWidget: '.cart-widget',

            // Offcanvas cart
            offCanvasCart: '.offcanvas',
            cartActions: '.cart-actions'
        };
    }
}
