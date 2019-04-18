const GeneralPageObject = require('../sw-general.page-object');

class OrderPageObject extends GeneralPageObject {
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

        this.browser
            .waitForElementVisible(`.sw-order-state-${scope}__${type}-state select[name=sw-field--selectedActionName]`)
            .click(`.sw-order-state-${scope}__${type}-state select[name=sw-field--selectedActionName]`)
            .setValue(
                `.sw-order-state-${scope}__${type}-state select[name=sw-field--selectedActionName]`,
                stateTitle
            )
            .click(this.elements.smartBarHeader)
            .waitForElementNotPresent('.sw-order-user-card .sw-loader__element');

        scope === 'select' ? this.browser.waitForElementVisible(stateColor) : null;
    }

    checkOrderHistoryEntry({ type, stateTitle, signal = 'neutral', position = 0 }) {
        const currentStatusIcon = `.sw-order-state__${signal}-icon-bg`;
        const item = `.sw-order-state-history-card__${type}-state .sw-order-state-history__entry--${position}`;

        this.browser
            .waitForElementVisible(`${item} ${currentStatusIcon}`)
            .assert.containsText(item, stateTitle);
    }
}

module.exports = (browser) => {
    return new OrderPageObject(browser);
};
