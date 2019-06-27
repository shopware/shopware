const GeneralPageObject = require('../sw-general.page-object');

export default class OrderPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);

        this.elements = {
            ...this.elements,
            ...{
                userMetadata: '.sw-order-user-card__metadata'
            }
        };
    }

    setOrderState({ stateTitle, type, signal = 'neutral', scope = 'select' }) {
        const stateColor = `.sw-order-state__${signal}-select`;

        cy.get(`.sw-order-state-${scope}__${type}-state select[name=sw-field--selectedActionName]`)
            .should('be.visible')
            .select(stateTitle, { force: true });
        cy.get(`.sw-order-state-${scope}__${type}-state .sw-loader__element`).should('not.exist');
        cy.get(this.elements.loader).should('not.exist');
        cy.get(this.elements.smartBarHeader).click();
        cy.get('.sw-order-user-card .sw-loader__element').should('not.exist');

        if (scope === 'select') {
            cy.get(stateColor).should('be.visible');
        }
    }

    checkOrderHistoryEntry({ type, stateTitle, signal = 'neutral', position = 0 }) {
        const currentStatusIcon = `.sw-order-state__${signal}-icon`;
        const item = `.sw-order-state-history-card__${type}-state .sw-order-state-history__entry--${position}`;

        cy.get('.sw-order-state-card').scrollIntoView();
        cy.get(`${item} ${currentStatusIcon}`).should('be.visible');
        cy.get(item).contains(stateTitle);
    }
}
