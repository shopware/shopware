export default class GeneralPageObject {
    constructor(browser) {
        this.browser = browser;

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
            dataGridHeader: '.sw-data-grid__header',
            dataGridColumn: '.sw-data-grid__cell',
            dataGridInlineEditSave: '.sw-data-grid__inline-edit-save',

            emptyState: '.sw-empty-state',
            contextMenu: '.sw-context-menu',
            contextMenuButton: '.sw-context-button__button',

            // Create/detail components
            primaryButton: '.sw-button--primary',
            dangerButton: '.sw-button--danger',
            cardTitle: '.sw-card__title',

            // Notifications
            alert: '.sw-alert',
            alertClose: '.sw-alert__close',
            notification: '.sw-notifications__notification',

            // Rule conditions
            conditionOrContainer: '.sw-condition-container__or-child',
            conditionAndContainer: '.sw-condition-container__and-child',
            subConditionContainer: '.container-condition-level__is--even',
            ruleFieldCondition: '.field--condition',
            orSpacer: '.condition-content__spacer--or',
            andSpacer: '.condition-content__spacer--and',
            baseCondition: '.sw-condition-base'
        };
    }

    fillLoremIpsumIntoSelector(selector, clearField = false) {
        this.browser
            .fillField(selector, 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.', clearField);
    }
}
