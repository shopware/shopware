// / <reference types="Cypress" />

describe('FirstRunWizard Test language Auto-Install', () => {
    beforeEach(() => {
        // Clean previous state and prepare Administration
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.setLocaleToEnGb();
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    // skipped because it has a dependency to the sbp, see NEXT-15818
    it.skip('@frw: Tests the auto-install of the first run wizard with dutch', () => {
        cy.visit(`${Cypress.env('admin')}#/sw/first/run/wizard/index`);

        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/extension/install/plugin/SwagLanguagePack`,
            method: 'POST'
        }).as('installPlugin');

        cy.route({
            url: `${Cypress.env('apiPath')}/_action/extension/activate/plugin/SwagLanguagePack`,
            method: 'PUT'
        }).as('activatePlugin');

        cy.route({
            url: `${Cypress.env('apiPath')}/search/locale`,
            method: 'POST'
        }).as('searchLocale');

        cy.route({
            url: `${Cypress.env('apiPath')}/_admin/snippets?locale=nl-NL`,
            method: 'GET'
        }).as('getAdminSnippets');

        // First run wizard modal should be visible
        cy.get('.sw-first-run-wizard-modal').should('be.visible');

        // Search for Shopware Language Pack
        cy.get('.sw-plugin-card').contains('Shopware Language Pack');

        // Install Shopware Language Pack plugin
        cy.get('.sw-plugin-card').contains('Shopware Language Pack').get('.button-plugin-install').click();

        // Wait for plugin install requests
        cy.wait(['@installPlugin', '@activatePlugin']).spread((installPlugin, activatePlugin) => {
            expect(installPlugin).to.have.property('status', 204);
            expect(activatePlugin).to.have.property('status', 204);
        })

        // Check if loader is not visible and content switch modal is shown
        cy.get('.sw-first-run-wizard-modal-content__page .sw-loader').should('not.exist');
        cy.get('.sw-first-run-wizard-confirmLanguageSwitch-modal').should('be.visible');

        // Select "Dutch" and continue
        cy.get('#sw-field--user-localeId').select('Dutch (Netherlands)');
        cy.get('#sw-field--user-pw').clearTypeAndCheck('shopware');
        cy.get('.sw-first-run-wizard-confirmLanguageSwitch-modal .sw-button--primary').click();

        // Wait for locale requests requests
        cy.wait(['@searchLocale', '@getAdminSnippets']).spread((searchLocale, getAdminSnippets) => {
            expect(searchLocale).to.have.property('status', 200);
            expect(getAdminSnippets).to.have.property('status', 200);
        })

        // The language switch reloads the page and the first run wizard should be visible again
        cy.get('.sw-first-run-wizard-modal').should('be.visible');

        cy.get('.sw-first-run-wizard-modal').contains('Welkom bij de Shopware 6 Administration');
    });

    it.skip('@frw: Should fail to install Lingala', () => {
        AddLanguageToLanguageTable('No Plugin Available Language', 'ln-CD', 'Lingala, Democratic Republic of the Congo');
        cy.visit(`${Cypress.env('admin')}#/sw/first/run/wizard/index`).then(() => {
            cy.get('.sw-notifications__notification--0 > .sw-alert__body').should('be.visible').then(() => {
                runTroughFirstRunWizard();
            });
            cy.get('.sw-context-button > .sw-icon > svg').click().get('.sw-notification-center-item__title')
                .contains('error');
        });
    });
});

/**
 * Adds a new language entry and checks if the creation was successful, fails if the language already existed
 * @param name
 * @param iso
 * @param contains
 */
function AddLanguageToLanguageTable(name, iso, contains) {
    cy.visit(`${Cypress.env('admin')}#/sw/settings/language/create`).then(() => {
        cy.get('input[name=sw-field--language-name]').type(name);
        cy.get('#iso-codes > .sw-block-field__block > .sw-select__selection > .sw-entity-single-select__selection > .sw-entity-single-select__selection-text')
            .type(iso).then(() => {
                cy.get('.sw-select-result-list__content').should('be.visible').contains(iso).click();
            });

        cy.get('#locales > .sw-block-field__block > .sw-select__selection > .sw-entity-single-select__selection > .sw-entity-single-select__selection-text')
            .type(iso).then(() => {
                cy.contains(contains).click();
            });
    }).then(() => {
        cy.get('.sw-button-process').click().then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/language/index?`);
            cy.contains(contains);
        });
    });
}

function runTroughFirstRunWizard() {
    cy.get('.footer-right > .sw-button').click().then(() => {
        cy.get('.footer-right > .sw-button--primary').click().then(() => {
            cy.get('.footer-right > :nth-child(1)').click().then(() => {
                cy.get('.footer-right > :nth-child(1)').click().then(() => {
                    cy.get('.footer-right > .sw-button').click().then(() => {
                        cy.get('.footer-right > .sw-button').click().then(() => {
                            cy.get('.footer-right > :nth-child(1)').click().then(() => {
                                cy.get('.footer-right > :nth-child(1)').click().then(() => {
                                    cy.get('.footer-right > .sw-button').click().then(() => {

                                    });
                                });
                            });
                        });
                    });
                });
            });
        });
    });
}
