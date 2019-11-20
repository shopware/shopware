// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************
//
//
// -- This is a parent command --
// Cypress.Commands.add("login", (email, password) => { ... })
//
//
// -- This is a child command --
// Cypress.Commands.add("drag", { prevSubject: 'element'}, (subject, options) => { ... })
//
//
// -- This is a dual command --
// Cypress.Commands.add("dismiss", { prevSubject: 'optional'}, (subject, options) => { ... })
//
//
// -- This is will overwrite an existing command --
// Cypress.Commands.overwrite("visit", (originalFn, url, options) => { ... })
import 'cypress-file-upload';

/**
 * Switches administration UI locale to EN_GB
 * @memberOf Cypress.Chainable#
 * @name setLocaleToEnGb
 * @function
 */
Cypress.Commands.add('setLocaleToEnGb', () => {
    return cy.window().then((win) => {
        win.localStorage.setItem('sw-admin-locale', Cypress.env('locale'));
    });
});

/**
 * Logs in to the Administration manually
 * @memberOf Cypress.Chainable#
 * @name login
 * @function
 * @param {Object} userType - The type of the user logging in
 */
Cypress.Commands.add('login', (userType) => {
    const types = {
        admin: {
            name: 'admin',
            pass: 'shopware'
        }
    };

    const user = types[userType];

    cy.visit('/admin');

    cy.contains('Username');
    cy.contains('Password');

    cy.get('#sw-field--username')
        .type(user.name)
        .should('have.value', user.name);
    cy.get('#sw-field--password')
        .type(user.pass)
        .should('have.value', user.pass);
    cy.get('.sw-login-login').submit();
    cy.contains('Dashboard');
});

/**
 * Types in an input element and checks if the content was correctly typed
 * @memberOf Cypress.Chainable#
 * @name typeAndCheck
 * @function
 * @param {String} value - The value to type
 */
Cypress.Commands.add('typeAndCheck', {
    prevSubject: 'element'
}, (subject, value) => {
    cy.wrap(subject).should('be.visible');
    cy.wrap(subject).type(value).should('have.value', value);
});

/**
 * Types in an input element and checks if the content was correctly typed
 * @memberOf Cypress.Chainable#
 * @name clearTypeAndCheck
 * @function
 * @param {String} value - The value to type
 */
Cypress.Commands.add('clearTypeAndCheck', {
    prevSubject: 'element'
}, (subject, value) => {
    cy.wrap(subject).should('be.visible');
    cy.wrap(subject).clear();
    cy.wrap(subject).clear(); // To make sure it's cleared
    cy.wrap(subject).type(value).should('have.value', value);
});

/**
 * Types in an swSelect field and checks if the content was correctly typed
 * @memberOf Cypress.Chainable#
 * @name typeMultiSelectAndCheck
 * @function
 * @param {String} value - Desired value of the element
 * @param {Object} [options={}] - Options concerning swSelect usage
 */
Cypress.Commands.add('typeMultiSelectAndCheck', {
    prevSubject: 'element'
}, (subject, value, options = {}) => {
    const resultPrefix = '.sw-select';
    const inputCssSelector = '.sw-select-selection-list__input';
    const searchTerm = options.searchTerm || value;
    const position = options.position || 0;

    // Request we want to wait for later
    cy.server();
    cy.route({
        url: '/api/v1/search/*',
        method: 'post'
    }).as('filteredResultCall');

    cy.wrap(subject).should('be.visible');

    // type in the search term if available
    if (options.searchTerm) {
        cy.get(`${subject.selector} ${inputCssSelector}`).type(searchTerm);
        cy.get(`${subject.selector} ${inputCssSelector}`).should('have.value', searchTerm);

        cy.wait('@filteredResultCall').then(() => {
            cy.get(`${resultPrefix}-option--${position}`).should('be.visible');

            cy.wait('@filteredResultCall').then(() => {
                cy.get('.sw-loader__element').should('not.exist');
            });
        });
        cy.get(`${resultPrefix}-option--${position}`).should('be.visible');
        cy.get(`${resultPrefix}-option--${position} .sw-highlight-text__highlight`).contains(value);

        // select the first result (or at another position)
        cy.get(`${resultPrefix}-option--${position}`)
            .click({force: true});
    } else {
        cy.wrap(subject).click();
        cy.get('.sw-select-result').should('be.visible');
        cy.contains('.sw-select-result', value).click();
    }

    // in multi selects we can check if the value is the selected item
    cy.get(`${subject.selector} .sw-select-selection-list__item-holder--0`).contains(value);

    // close search results
    cy.get(`${subject.selector} ${inputCssSelector}`).type('{esc}');
    cy.get(`${subject.selector} .sw-select-result-list`).should('not.exist');
    return this;
});

/**
 * Types in an swSelect field
 * @memberOf Cypress.Chainable#
 * @name typeSingleSelect
 * @function
 * @param {String} value - Desired value of the element
 * @param {String} selector - selector of the element
 */
Cypress.Commands.add('typeSingleSelect', {
    prevSubject: 'element'
}, (subject, value, selector) => {
    const resultPrefix = '.sw-select';
    const inputCssSelector = `.sw-select__selection input`;

    cy.wrap(subject).should('be.visible');
    cy.wrap(subject).click();

    // type in the search term if available
    if (value) {
        cy.get('.sw-select-result-list').should('be.visible');
        cy.get(`${selector} ${inputCssSelector}`).clear();
        cy.get(`${selector} ${inputCssSelector}`).type(value);
        cy.get(`${selector} ${inputCssSelector}`).should('have.value', value);

        // Wait the debounce time for the search to begin
        cy.wait(500);

        cy.get(`${selector}.sw-loader__element`).should('not.exist');

        cy.get(`${selector} .is--disabled`)
            .should('not.exist');

        cy.get(`${selector} .sw-select-result__result-item-text`)
            .should('be.visible');

        cy.get(`${selector} .sw-select-result__result-item-text`)
            .contains(value).click({force: true});
    } else {
        // Select the first element
        cy.get(`${resultPrefix}-option--0`).click({force: true});
    }

    cy.get(`${selector} .sw-select-result-list`).should('not.exist');

    return this;
});

/**
 * Types in an swSelect field and checks if the content was correctly typed
 * @memberOf Cypress.Chainable#
 * @name typeSingleSelectAndCheck
 * @function
 * @param {String} value - Desired value of the element
 * @param {String} selector - Options concerning swSelect usage
 */
Cypress.Commands.add('typeSingleSelectAndCheck', {
    prevSubject: 'element'
}, (subject, value, selector) => {
    cy.get(subject).typeSingleSelect(value, selector);

    // expect the placeholder for an empty select field not be shown and search for the value
    cy.get(`${subject.selector} .sw-select__selection .is--placeholder`).should('not.exist');
    cy.get(`${subject.selector} .sw-select__selection`).contains(value);

    return this;
});

/**
 * Types in an legacy swSelect field and checks if the content was correctly typed
 * @memberOf Cypress.Chainable#
 * @name typeLegacySelectAndCheck
 * @function
 * @param {String} value - Desired value of the element
 * @param {Object} options - Options concerning swSelect usage
 */
Cypress.Commands.add('typeLegacySelectAndCheck', {
    prevSubject: 'element'
}, (subject, value, options) => {
    const inputCssSelector = (options.isMulti) ? '.sw-select__input' : '.sw-select__input-single';

    cy.wrap(subject).should('be.visible');

    if (options.clearField && options.isMulti) {
        cy.get(`${subject.selector} .sw-label__dismiss`).click();
        cy.get(`${subject.selector} ${'.sw-label'}`).should('not.exist');
    }

    if (!options.isMulti) {
        // open results list
        cy.wrap(subject).click();
        cy.get('.sw-select__results').should('be.visible');
    }

    // type in the search term if available
    if (options.searchTerm) {
        cy.get(`${subject.selector} ${inputCssSelector}`).type(options.searchTerm);
        cy.get(`${subject.selector} .sw-select__indicators .sw-loader`).should('not.exist');
        cy.get('.sw-select__results').should('be.visible');
        cy.get('.sw-select-option--0 .sw-select-option__result-item-text').contains(value);
    }

    // select the first result
    cy.get(`${subject.selector} ${inputCssSelector}`).type('{enter}');

    if (!options.isMulti) {
        // expect the placeholder for an empty select field not be shown and search for the value
        cy.get(`${subject.selector} .sw-select__placeholder`).should('not.exist');
        cy.get(`${subject.selector} .sw-select__single-selection`).contains(value);

        return this;
    }

    // in multi selects we can check if the value is a selected item
    cy.get(`${subject.selector} .sw-select__selection-item`).contains(value);

    // close search results
    cy.get(`${subject.selector} ${inputCssSelector}`).type('{esc}');
    return this;
});

/**
 * Assert that an SW Grid with a row containing a given label also contains another column with a given value
 * @memberOf Cypress.Chainable#
 * @name assertRowWithLabelContains
 * @function
 * @param {String|RegExp} columnValue - The value which should exists in the row
 * @param {String} columnSelector - Selector to select the value row
 * @param {String|RegExp} labelColumnValue - Label of the row to assert
 * @param {String} [labelColumnSelector] - Selector to select the label row
 */
Cypress.Commands.add('assertRowWithLabelContains', {
    prevSubject: 'element'
}, (subject, columnValue, columnSelector, labelColumnValue, labelColumnSelector = '.sw-data-grid__cell--label') => {

    subject.children()
        .get(labelColumnSelector)
        .contains(labelColumnValue)
        .parent().parent().parent()
        .within(($row) => {
            cy.get(`${columnSelector} > .sw-data-grid__cell-content`).contains(columnValue)
        });
});



/**
 * Types in the global search field and verify search terms in url
 * @memberOf Cypress.Chainable#
 * @name typeAndCheckSearchField
 * @function
 * @param {String} value - The value to type
 */
Cypress.Commands.add('typeAndCheckSearchField', {
    prevSubject: 'element'
}, (subject, value) => {

    // Request we want to wait for later
    cy.server();
    cy.route({
        url: '/api/v1/search/**',
        method: 'post'
    }).as('searchResultCall');

    cy.wrap(subject).type(value).should('have.value', value);

    cy.wait('@searchResultCall').then((xhr) => {
        expect(xhr).to.have.property('status', 200);

        cy.url().should('include', encodeURI(value));
    });
});

/**
 * Wait for a notification to appear and check its message
 * @memberOf Cypress.Chainable#
 * @name awaitAndCheckNotification
 * @function
 * @param {String} message - The message to look for
 * @param {Object}  [options={}] - Options concerning the notification
 */
Cypress.Commands.add('awaitAndCheckNotification', (message, options = {
    position: 0,
    collapse: true
}) => {
    const notification = `.sw-notifications__notification--${options.position}`;

    cy.get(`${notification} .sw-alert__message`).should('be.visible').contains(message);

    if (options.collapse) {
        cy.get(`${notification} .sw-alert__close`).click().should('not.exist');
    }
});

/**
 * Click context menu in order to cause a desired action
 * @memberOf Cypress.Chainable#
 * @name clickContextMenuItem
 * @function
 * @param {String} menuButtonSelector - The message to look for
 * @param {String} menuOpenSelector - The message to look for
 * @param {Object} [scope=null] - Options concerning the notification
 * @param {String} [menuButtonText=null] - Text of the menu button
 */
Cypress.Commands.add('clickContextMenuItem', (menuButtonSelector, menuOpenSelector, scope = null, menuButtonText = "") => {
    const contextMenuCssSelector = '.sw-context-menu';
    const activeContextButtonCssSelector = '.is--active';

    if (scope != null) {
        cy.get(scope).should('be.visible');
        cy.get(`${scope} ${menuOpenSelector}`).click({force: true});

        if (scope.includes('row')) {
            cy.get(`${menuOpenSelector}${activeContextButtonCssSelector}`).should('be.visible');
        }
    } else {
        cy.get(menuOpenSelector).should('be.visible').click({force: true});
    }

    cy.get(contextMenuCssSelector).should('be.visible');
    let element = cy.get(menuButtonSelector);
    if(menuButtonText !== "") {
        element = element.contains(menuButtonText);
    }
    element.click();
    cy.get(contextMenuCssSelector).should('not.exist');
});

/**
 * Navigate to module by clicking the corresponding main menu item
 * @memberOf Cypress.Chainable#
 * @name clickMainMenuItem
 * @function
 * @param {Object} obj - Menu options
 * @param {String} obj.targetPath - The url the user should end with
 * @param {String} obj.mainMenuId - Id of the Main Menu item
 * @param {String} [obj.subMenuId=null] - Id of the sub menu item
 */
Cypress.Commands.add('clickMainMenuItem', ({targetPath, mainMenuId, subMenuId = null}) => {
    const finalMenuItem = `.sw-admin-menu__item--${mainMenuId}`;

    cy.get('.sw-admin-menu').should('be.visible').then(() => {
        if (subMenuId) {
            cy.get(finalMenuItem).click();
            cy.get(`.sw-admin-menu__item--${mainMenuId} .router-link-active`).should('be.visible');
            cy.get(`.${subMenuId}`).click();
        } else {
            cy.get(finalMenuItem).should('be.visible').click();
        }
    });
    cy.url().should('include', targetPath);
});

/**
 * Reload listing using sidebar button
 * @memberOf Cypress.Chainable#
 * @name reloadListing
 * @function
 * @param {Object} [reloadSelectors=null] - The message to look for
 * @param {String} reloadSelectors.reloadButtonSelector - The message to look for
 * @param {String} reloadSelectors.loadingIndicatorSelector - The message to look for
 */
Cypress.Commands.add('reloadListing', (reloadSelectors = {
    reloadButtonSelector: '.sw-sidebar-navigation-item .icon--default-arrow-360-left',
    loadingIndicatorSelector: 'sw-data-grid-skeleton'
}) => {
    cy.get(reloadSelectors.reloadButtonSelector).should('be.visible');
    cy.get(reloadSelectors.reloadButtonSelector).click();
    cy.get(reloadSelectors.loadingIndicatorSelector).should('not.exist');
});

/**
 * Click user menu
 * @memberOf Cypress.Chainable#
 * @name openUserActionMenu
 * @function
 */
Cypress.Commands.add('openUserActionMenu', () => {
    cy.get('.sw-admin-menu__user-actions-toggle').should('be.visible');

    cy.get('.sw-admin-menu__user-actions-indicator').then(($btn) => {
        if ($btn.hasClass('icon--small-arrow-medium-up')) {
            cy.get('.sw-admin-menu__user-actions-toggle').click();
            cy.get('.sw-admin-menu__logout-action').should('be.visible');
        }
    });
    return this;
});

/**
 * Selects a date in date field component
 * @memberOf Cypress.Chainable#
 * @name fillAndCheckDateField
 * @function
 * @param {String} value - The value to type
 * @param {String} selector - Field selector
 */
Cypress.Commands.add('fillAndCheckDateField', {
    prevSubject: 'element'
}, (subject, value, selector) => {
    // Get selector for both fields
    cy.get(subject).should('be.visible');

    const hiddenDateFieldSelector = `${selector} .flatpickr-input:nth-of-type(1)`;
    const visibleDateFieldSelector = `${selector} .flatpickr-input.form-control`;

    cy.get(hiddenDateFieldSelector).should('exist');
    cy.get(visibleDateFieldSelector).should('be.visible');

    // Set hidden ISO date
    const dateParts = value.split(' ');
    let isoDate = '';

    // no Time
    if (dateParts.length === 1) {
        isoDate = `${dateParts[0]}T00:00:00+00:00`;
    } else {
        isoDate = `${dateParts.join('T')}:00+00:00`;
    }
    cy.get(hiddenDateFieldSelector).then(elem => {
        elem.val(isoDate);
    });

    // Set visible date
    cy.get(visibleDateFieldSelector).type(value);
    cy.get(visibleDateFieldSelector).type('{enter}');
});

/**
 * Performs a drag and drop operation
 * @memberOf Cypress.Chainable#
 * @name dragTo
 * @param {String} targetEl - The target element to drag source to
 * @function
 */
Cypress.Commands.add('dragTo', { prevSubject: 'element' }, (subject, targetEl) => {
    cy.wrap(subject)
        .trigger('mousedown', { buttons: 1 });

    cy.get('.is--dragging').should('be.visible');
    cy.get(targetEl)
        .should('be.visible')
        .trigger('mouseenter')
        .trigger('mousemove', 'center')
        .should('have.class', 'is--valid-drop')
        .trigger('mouseup');
});
