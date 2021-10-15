import elements from '../sw-general.page-object';

export default class OrderPageObject {
    constructor() {
        this.elements = {
            ...elements,
            ...{
                smartBarSave: '.sw-order-detail__smart-bar-save-button',
                userMetadata: '.sw-order-user-card__metadata',
                stateSelects: {
                    orderTransactionStateSelect: '.sw-order-state-select-v2__order_transaction',
                    orderDeliveryStateSelect: '.sw-order-state-select-v2__order_delivery',
                    orderStateSelect: '.sw-order-state-select-v2__order',
                },
                tabs: {
                    activeTab: '.sw-tabs__content > .sw-tabs-item.sw-tabs-item--active',
                    detailsTab: '.sw-order-detail__tabs-tab-details',
                    documentsTab: '.sw-order-detail__tabs-tab-documents',
                    generalTab: '.sw-order-detail__tabs-tab-general',
                    details: {
                        disableAutomaticPromotionsSwitch: '.sw-order-promotion-field .sw-field--switch__input',
                        openStateHistoryModalButton: '.sw-order-detail-state-card__state-history-button',
                    },
                    documents: {
                        addDocumentButton: '.sw-order-document-grid-button',
                        documentGrid: '.sw-order-detail-base__document-grid',
                        documentSettingsModal: '.sw-order-document-settings-modal__settings-modal',
                        documentTypeModal: '.sw-order-select-document-type-modal',
                        documentTypeModalRadios: '.sw-order-select-document-type-modal__radio-field',
                    },
                    general: {
                        // General info summary card
                        addProductButton: '.sw-order-line-items-grid__actions-container-add-product-btn',
                        generalInfoCard: '.sw-order-detail-base__general-info',
                        gridCard: '.sw-order-detail-general__line-item-grid-card',
                        summary: '.sw-order-detail-base__general-info__summary',
                        summaryMainHeader: '.sw-order-detail-base__general-info__summary-main-header',
                        summaryMainTotal: '.sw-order-detail-base__general-info__summary-main-total',
                        summarySubDescription: '.sw-order-detail-base__general-info__summary-sub-description',
                        summarySubLastChangedTime: '.sw-order-detail-base__general-info__summary-sub-last-changed-time',
                        summarySubLastChangedUser: '.sw-order-detail-base__general-info__summary-sub-last-changed-user',
                        summaryStateSelects: '.sw-order-detail-base__general-info__order-state',
                        summaryTagSelect: '.sw-order-detail-base__general-info__order-tags',
                    },
                },
            },
        };
    }

    setOrderState({
        stateTitle,
        type,
        signal = 'neutral',
        scope = 'select',
        call = null
    }) {
        const stateColor = `.sw-order-state__${signal}-select`;
        const callType = type === 'payment' ? '_transaction' : '';
        const stateSelector = this.getStatesSelector(type, scope);

        let stateMachineType;

        cy.onlyOnFeature('FEATURE_NEXT_7530', () => {
            switch (type) {
                case 'payment':
                    stateMachineType = 'order_transaction';
                    break;
                case 'delivery':
                    stateMachineType = 'order_delivery';
                    break;
                case 'order':
                    stateMachineType = 'order';
                    break;
                default:
                    console.error(`Unknown state-machine type ${type}`);
            }
        });

        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/order${callType}/**/state/${call}`,
            method: 'post',
        }).as(`${call}Call`);

        cy.get(stateSelector).scrollIntoView();

        cy.skipOnFeature('FEATURE_NEXT_7530', () => {
            cy.get(stateSelector)
                .should('be.visible')
                .select(stateTitle);
        });

        cy.onlyOnFeature('FEATURE_NEXT_7530', () => {
            cy.get(stateSelector)
                .should('be.visible')
                .typeSingleSelect(
                    stateTitle,
                    stateSelector
                );
        });

        cy.get('.sw-order-state-change-modal')
            .should('be.visible');

        cy.get('.sw-order-state-change-modal-attach-documents__button')
            .click();

        cy.wait(`@${call}Call`).its('response.statusCode').should('equal', 200);

        cy.skipOnFeature('FEATURE_NEXT_7530', () => {
            cy.get(`${stateSelector} .sw-loader__element`).should('not.exist');
        });

        cy.onlyOnFeature('FEATURE_NEXT_7530', () => {
            cy.get(stateSelector)
                .find('.sw-single-select__selection-input')
                .should('have.attr', 'placeholder', stateTitle);
        });

        cy.get(this.elements.loader).should('not.exist');
        cy.get(this.elements.smartBarHeader).click();

        if (scope === 'select') {
            cy.get(stateColor).should('be.visible');
        }
    }

    checkOrderHistoryEntry({ type, stateTitle, signal = 'neutral', position = 0 }) {
        cy.skipOnFeature('FEATURE_NEXT_7530', () => {
            const currentStatusIcon = `.sw-order-state__${signal}-icon`;
            const item = `.sw-order-state-history-card__${type}-state .sw-order-state-history__entry--${position}`;

            cy.get('.sw-order-state-card').scrollIntoView();
            cy.get('.sw-order-state-card').should('be.visible');
            cy.get(`${item} ${currentStatusIcon}`).should('be.visible');
            cy.get(item).contains(stateTitle);
        });

        cy.onlyOnFeature('FEATURE_NEXT_7530', () => {
            cy.get(this.elements.tabs.details.openStateHistoryModalButton)
                .first()
                .should('exist')
                .click({ force: true });

            cy.get('.sw-modal').should('be.visible');

            let dataGridRow = cy.get(`.sw-modal .sw-data-grid__row--${position}`);

            if (!position) {
                dataGridRow = cy.get('.sw-modal .sw-data-grid__row').last();
            }

            dataGridRow.should('be.visible');

            if (type === 'payment') {
                type = 'transaction';
            }

            const dataGridCell = dataGridRow.find(`.sw-data-grid__cell--${type}`);

            dataGridCell.contains(stateTitle);

            cy.get('.sw-modal__footer .sw-button')
                .should('be.visible')
                .click();

            cy.get('.sw-modal').should('not.exist');
        });
    }

    getStatesSelector(type, scope) {
        cy.skipOnFeature('FEATURE_NEXT_7530', () => {
            return `.sw-order-state-${scope}__${type}-state select[name=sw-field--selectedActionName]`;
        });

        switch (type) {
            case 'payment':
                return this.elements.stateSelects.orderTransactionStateSelect;
            case 'delivery':
                return this.elements.stateSelects.orderDeliveryStateSelect;
            case 'order':
                return this.elements.stateSelects.orderStateSelect;
            default:
                console.error(`Unknown state type ${type}`);
        }
    }

    changeActiveTab(tab) {
        let tabElement;

        switch (tab) {
            case 'general':
                tabElement = cy.get(this.elements.tabs.generalTab);
                break;
            case 'details':
                tabElement = cy.get(this.elements.tabs.detailsTab);
                break;
            case 'documents':
                tabElement = cy.get(this.elements.tabs.documentsTab);
                break;
            default:
                console.error(`Unknown tab ${tab}`);
        }

        tabElement
            .should('exist')
            .scrollIntoView()
            .click();

        tabElement.should('have.class', 'sw-tabs-item--active');

        cy.get(this.elements.loader).should('not.exist');
    }
}
