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

            dataGridRow: '.sw-data-grid__row',
            dataGridColumn: '.sw-data-grid__cell',
            dataGridInlineEditSave: '.sw-data-grid__inline-edit-save',

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
            alertClose: '.sw-alert__close',
            notification: '.sw-notifications__notification'
        };
    }

    fillLoremIpsumIntoSelector(selector, clearField = false) {
        this.browser
            .fillField(selector, 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.', clearField);
    }
}
