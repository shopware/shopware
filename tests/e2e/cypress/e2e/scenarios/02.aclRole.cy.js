/// <reference types="Cypress" />
describe('create role with different permissions', () => {
    it('@package: create role', { tags: ['pa-services-settings', 'quarantined'] }, () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/acl-role`,
            method: 'POST',
        }).as('aclRoleSearch');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/acl-role`,
            method: 'POST',
        }).as('aclRoleSave');

        const permission = '.sw-users-permissions-permissions-grid';
        const detailedPermission = '.sw-users-permissions-detailed-permissions-grid';
        const additionalPermission = '.sw_users_permissions_additional_permissions_system';

        //go to sw/users/permissions/index
        cy.visit(`${Cypress.env('admin')}#/sw/settings/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-settings__tab-system').click();
        cy.get('#sw-users-permissions').click();

        //click create role button and fill in the required blanks
        cy.get('.sw-users-permissions-role-listing__toolbar > .sw-container > .sw-button').click();
        cy.get('#sw-field--role-name').clearTypeAndCheck('new Role');
        cy.get('#sw-field--role-description').clearTypeAndCheck('Description Test');

        //add different permissions
        cy.get(`${permission}__parent_settings > ${permission}__role_creator`).click();
        cy.get(`${permission}__parent_catalogues > ${permission}__role_creator`).click();
        cy.get(`${permission}__parent_orders > ${permission}__role_creator`).click();
        cy.get(`${permission}__entry_payment > ${permission}__all`).click();
        cy.get(`${additionalPermission}_clear_cache > .sw-field--switch__content`)
            .find('[type="checkbox"]').check();
        cy.get(`${additionalPermission}_plugin_maintain > .sw-field--switch__content`)
            .find('[type="checkbox"]').check();
        cy.get('div[class*="user_update_profile"')
            .find('[type="checkbox"]').check();

        //save new Role
        cy.get('.sw-button-process').click();
        cy.get('#sw-field--confirm-password').clearTypeAndCheck('shopware');
        cy.get('.sw-modal__footer > .sw-button--primary > .sw-button__content').click();
        cy.wait('@aclRoleSave').its('response.statusCode').should('equal', 204);
        cy.get('.sw-modal__dialog').should('not.exist');
        cy.get('.sw-button__loader').should('not.exist');

        //go to User Permissions Page and verify if it is save
        cy.get('.icon--regular-chevron-left > svg').click();
        cy.wait('@aclRoleSearch').its('response.statusCode').should('equal', 200);
        cy.get('.sw-users-permissions-role-listing__toolbar > .sw-container > .sw-button').should('be.visible');
        cy.get('.sw-data-grid__row--0 > .sw-data-grid__cell--name > .sw-data-grid__cell-content')
            .should('contain', 'new Role');

        //click new Role go general tab and check permissions
        cy.get('.sw-data-grid__row--0 > .sw-data-grid__cell--name > .sw-data-grid__cell-content > a').click();
        cy.get(`${permission}__parent_orders > ${permission}__role_creator`)
            .find('[type="checkbox"]').should('be.checked');
        cy.get(`${permission}__parent_catalogues > ${permission}__role_creator`)
            .find('[type="checkbox"]').should('be.checked');
        cy.get(`${permission}__parent_content > ${permission}__role_creator`)
            .find('[type="checkbox"]').should('not.be.checked');
        cy.get(`${additionalPermission}_system_config > .sw-field--switch__content`)
            .find('[type="checkbox"]').should('not.be.checked');
        cy.get(`${additionalPermission}_plugin_maintain > .sw-field--switch__content`)
            .find('[type="checkbox"]').should('be.checked');
        cy.get(`${additionalPermission}_clear_cache > .sw-field--switch__content`)
            .find('[type="checkbox"]').should('be.checked');

        //switch to detailed privilleges and check permissions
        cy.get('.sw-tabs__content').contains('Gedetailleerde bevoegdheden').click();
        cy.get(`${detailedPermission}__entry_acl_role > ${detailedPermission}__role_create`)
            .find('[type="checkbox"]').should('be.disabled');
        cy.get(`${detailedPermission}__entry_cms_section > ${detailedPermission}__role_delete`)
            .find('[type="checkbox"]').should('be.disabled');
        cy.get(`${detailedPermission}__entry_plugin > ${detailedPermission}__role_create`)
            .find('[type="checkbox"]').should('be.enabled');
    });
});
