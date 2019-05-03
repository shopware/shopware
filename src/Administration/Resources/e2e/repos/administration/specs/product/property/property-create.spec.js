const propertyPage = require('administration/page-objects/module/sw-property.page-object.js');

module.exports = {
    '@tags': ['product', 'property', 'property-create', 'create'],
    'open product listing': (browser) => {
        const page = propertyPage(browser);

        browser
            .openMainMenuEntry({
                targetPath: '#/sw/property/index',
                mainMenuId: 'sw-catalogue',
                subMenuId: 'sw-property'
            })
            .expect.element(page.elements.smartBarAmount).to.have.text.that.equals('(0)');
    },
    'add property group': (browser) => {
        const page = propertyPage(browser);

        browser
            .click('a[href="#/sw/property/create"]')
            .assert.urlContains('#/sw/property/create')
            .expect.element(page.elements.cardTitle).to.have.text.that.equals('Basic information');

        browser
            .fillField('input[name=sw-field--group-name]', 'Coleur')
            .fillSelectField('select[name=sw-field--group-displayType]', 'Color')
            .click(page.elements.propertySaveAction)
            .waitForElementVisible('.icon--small-default-checkmark-line-medium');
    },
    'verify property in listing': (browser) => {
        const page = propertyPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .refresh()
            .expect.element(`${page.elements.gridRow}--0 a`).to.have.text.that.equals('Coleur');
    },
    'add option to property group': (browser) => {
        const page = propertyPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-context-menu-item',
                scope: `${page.elements.gridRow}--0`
            })
            .expect.element(page.elements.cardTitle).to.have.text.that.equals('Basic information');

        browser
            .getLocationInView('.sw-property-option-list')
            .click('.sw-property-option-list__add-button')
            .waitForElementVisible('.sw-modal')
            .fillField('input[name=sw-field--currentOption-name]', 'Bleu')
            .fillField('input[name=sw-field--currentOption-position]', '1')
            .fillField('input[name=sw-field--currentOption-colorHexCode]', '#000088')
            .click(`${page.elements.modal} ${page.elements.primaryButton}`)
            .waitForElementNotPresent(page.elements.modal)
            .waitForElementNotPresent('.icon--small-default-checkmark-line-medium')
            .click(page.elements.propertySaveAction)
            .waitForElementVisible('.icon--small-default-checkmark-line-medium');
    },
    'verify new options in listing': (browser) => {
        const page = propertyPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .refresh()
            .expect.element(`${page.elements.gridRow}--0`).to.have.text.that.contains('Bleu');
    }
};
