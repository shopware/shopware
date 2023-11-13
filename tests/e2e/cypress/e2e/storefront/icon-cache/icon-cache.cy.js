describe('Test if info icon is visible correctly', () => {
    beforeEach(() => {
        cy.visit('/');
    });

    it('@icon-cache: Check if info icon is visible', () => {
        cy.get('path#icons-default-info').should('be.visible');
    });
});
