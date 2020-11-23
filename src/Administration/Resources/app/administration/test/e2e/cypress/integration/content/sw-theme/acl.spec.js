// / <reference types="Cypress" />

import elements from '../../../support/pages/sw-general.page-object';

describe('Theme: Test ACL privileges', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.viewport(1920, 1080);
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@content: has no access to theme module', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'product',
                role: 'viewer'
            }
        ]).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/theme/manager/index`);
        });

        // open property without permissions
        cy.get('.sw-privilege-error__access-denied-image').should('be.visible');
        cy.get('h1').contains('Access denied');
        cy.get('.sw-property-list').should('not.exist');

        // see menu without theme menu item
        cy.get('.sw-admin-menu__navigation-list-item.sw-theme-manager').should('not.exist');
    });

    it('@content: can view theme', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'theme',
                role: 'viewer'
            }
        ]);

        // Navigate to theme manager
        cy.get('.sw-admin-menu__item--sw-content').click();
        cy.get('.sw-admin-menu__navigation-list-item.sw-theme-manager').click();

        // Ensure theme name
        cy.get('.sw-theme-list-item')
            .last()
            .find('.sw-theme-list-item__title')
            .contains('Shopware default theme');

        // Click theme actions
        cy.get('.sw-theme-list-item')
            .last()
            .find('.sw-theme-list-item__options')
            .click({ force: true });

        // Ensure all edit actions are disabled
        cy.get(`${elements.contextMenu} .sw-theme-list-item__option-preview`).should('have.class', 'is--disabled');
        cy.get(`${elements.contextMenu} .sw-theme-list-item__option-preview-remove`).should('have.class', 'is--disabled');
        cy.get(`${elements.contextMenu} .sw-theme-list-item__option-rename`).should('have.class', 'is--disabled');
        cy.get(`${elements.contextMenu} .sw-theme-list-item__option-duplicate`).should('have.class', 'is--disabled');

        // Switch to list mode
        cy.get('.sw-theme-list__actions-mode').click();

        // Ensure theme name and click actions menu manually
        cy.get(`${elements.dataGridRow}--0`).contains('Shopware default theme');
        cy.get(`${elements.dataGridRow}--0 ${elements.contextMenuButton}`).click();

        // Ensure all edit actions are disabled and close menu afterwards
        cy.get(`${elements.contextMenu} .sw-theme-list-item__option-rename`).should('have.class', 'is--disabled');
        cy.get(`${elements.contextMenu} .sw-theme-list-item__option-duplicate`).should('have.class', 'is--disabled');
        cy.get(`${elements.dataGridRow}--0 ${elements.contextMenuButton}`).click();

        // View theme
        cy.clickContextMenuItem(
            `${elements.contextMenu} .sw-theme-list-item__option-edit`,
            elements.contextMenuButton,
            `${elements.dataGridRow}--0`
        );

        cy.get('.sw-theme-manager-detail__info-name').contains('Shopware default theme');

        // Inputs should be visible but disabled
        cy.get('.sw-colorpicker .sw-colorpicker__input').first().should('have.attr', 'disabled');

        // Save action should be disabled
        cy.get('.smart-bar__actions .sw-button--primary').should('have.attr', 'disabled');
    });

    it('@content: can edit theme', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/theme/*`,
            method: 'patch'
        }).as('saveTheme');

        cy.loginAsUserWithPermissions([
            {
                key: 'theme',
                role: 'viewer'
            },
            {
                key: 'theme',
                role: 'editor'
            }
        ]);

        // Navigate to theme manager
        cy.get('.sw-admin-menu__item--sw-content').click();
        cy.get('.sw-admin-menu__navigation-list-item.sw-theme-manager').click();

        // Open theme
        cy.get('.sw-theme-list-item')
            .last()
            .find('.sw-theme-list-item__title')
            .contains('Shopware default theme')
            .click();

        // Change color value
        cy.get('.sw-colorpicker .sw-colorpicker__input').first().clear().typeAndCheck('#000');

        // Perform save action
        cy.get('.smart-bar__actions .sw-button-process.sw-button--primary').click();
        cy.get('.sw-modal .sw-button--primary').click();

        cy.wait('@saveTheme').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            cy.get('.sw-colorpicker .sw-colorpicker__input').first().should('have.value', '#000');
        });
    });

    it('@content: can create theme via duplicate functionality', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/theme`,
            method: 'post'
        }).as('duplicateTheme');

        cy.loginAsUserWithPermissions([
            {
                key: 'theme',
                role: 'viewer'
            },
            {
                key: 'theme',
                role: 'editor'
            },
            {
                key: 'theme',
                role: 'creator'
            }
        ]);

        // Navigate to theme manager
        cy.get('.sw-admin-menu__item--sw-content').click();
        cy.get('.sw-admin-menu__navigation-list-item.sw-theme-manager').click();

        // Click theme actions
        cy.get('.sw-theme-list-item')
            .last()
            .find('.sw-theme-list-item__options')
            .click({ force: true });

        // Click on duplicate action
        cy.get(`${elements.contextMenu} .sw-theme-list-item__option-duplicate`).click();

        // Check modal and confirm theme name
        cy.get('.sw_theme_manager__duplicate-modal').should('be.visible');
        cy.get('#sw-field--newThemeName').typeAndCheck('New Theme');
        cy.get('.sw_theme_manager__duplicate-modal .sw-button--primary').click();
        cy.get('.sw_theme_manager__duplicate-modal').should('not.be.visible');

        // Verify new theme data
        cy.wait('@duplicateTheme').then((xhr) => {
            expect(xhr).to.have.property('status', 204);

            cy.get('.sw-theme-manager-detail__info-name').contains('New Theme');
            cy.get('.sw-theme-manager-detail__inheritance').should('be.visible');
        });
    });

    it('@content: can delete theme', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/theme/*`,
            method: 'delete'
        }).as('deleteTheme');

        cy.createDefaultFixture('theme').then(() => {
            cy.loginAsUserWithPermissions([
                {
                    key: 'theme',
                    role: 'viewer'
                },
                {
                    key: 'theme',
                    role: 'editor'
                },
                {
                    key: 'theme',
                    role: 'creator'
                },
                {
                    key: 'theme',
                    role: 'deleter'
                }
            ]);
        });

        // Navigate to theme manager
        cy.get('.sw-admin-menu__item--sw-content').click();
        cy.get('.sw-admin-menu__navigation-list-item.sw-theme-manager').click();

        // Open theme actions of desired theme
        cy.get('.sw-theme-list__list-grid-content')
            .contains('E2E Theme')
            .closest('.sw-theme-list-item')
            .find('.sw-theme-list-item__options')
            .click({ force: true });

        // Perform delete action
        cy.get(`${elements.contextMenu} .sw-theme-list-item__option-delete`).click();
        cy.get('.sw-modal').should('be.visible');
        cy.get('.sw-modal .sw-button--danger').click();
        cy.get('.sw-modal').should('not.be.visible');

        cy.wait('@deleteTheme').then((xhr) => {
            expect(xhr).to.have.property('status', 204);

            // Ensure deleted theme is not present
            cy.get('.sw-theme-list__list-grid-content')
                .contains('E2E Theme')
                .should('not.exist');
        });
    });
});
