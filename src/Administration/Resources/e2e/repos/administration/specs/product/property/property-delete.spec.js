const propertyPage = require('administration/page-objects/module/sw-property.page-object.js');

module.exports = {
    '@tags': ['product', 'property', 'property-delete', 'delete'],
    before: (browser, done) => {
        return global.PropertyFixtureService.setPropertyFixture({
            options: [{ name: 'Red' }, { name: 'Yellow' }, { name: 'Green' }]
        }).then(() => {
            done();
        });
    },
    'open product listing': (browser) => {
        const page = propertyPage(browser);

        browser
            .openMainMenuEntry({
                targetPath: '#/sw/property/index',
                mainMenuId: 'sw-product',
                subMenuId: 'sw-property'
            })
            .expect.element(`${page.elements.gridRow}--0 a`).to.have.text.that.equals('Color');
    },
    'open property and delete option': (browser) => {
        const page = propertyPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-context-menu-item',
                scope: `${page.elements.gridRow}--0`
            })
            .expect.element(page.elements.cardTitle).to.have.text.that.equals('Basic information');

        browser
            .getLocationInView('.sw-property-option-list')
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: `${page.elements.contextMenu}-item--danger`,
                scope: `${page.elements.gridRow}--0`
            })
            .waitForElementVisible(`${page.elements.gridRow}--0.is--deleted`)
            .click(page.elements.propertySaveAction)
            .checkNotification('Property "Color" has been saved successfully.')
            .waitForElementNotPresent(`${page.elements.gridRow}--2`);
    },
    'delete property in listing': (browser) => {
        const page = propertyPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .expect.element(`${page.elements.gridRow}--0 a`).to.have.text.that.equals('Color');

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: `${page.elements.contextMenu}-item--danger`,
                scope: `${page.elements.gridRow}--0`
            })
            .expect.element(`${page.elements.modal} .sw-property-list__confirm-delete-text`).to.have.text.that
            .contains('Are you sure you really want to delete the property "Color"?');

        browser
            .click(`${page.elements.modal}__footer button${page.elements.primaryButton}`)
            .waitForElementNotPresent(page.elements.modal)
            .waitForElementVisible(page.elements.emptyState)
            .expect.element(page.elements.smartBarAmount).to.have.text.that.contains('(0)');
    }
};
