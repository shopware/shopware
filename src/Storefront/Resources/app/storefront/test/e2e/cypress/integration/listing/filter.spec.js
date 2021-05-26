/// <reference types="cypress" />

import generalPageObject from "../../support/pages/general.page-object";

describe("Filter on startpage", () => {
    beforeEach(() => {
        cy.visit("/");
    });

    it("Filter for manufacturer", () => {
        const page = new generalPageObject();

        const actualItems = 24;
        const filteredItems = 1;
        const manufacturer = "Armstrong Group";

        cy.get(page.elements.productCard).as("productCard");

        cy.get("@productCard").should("have.length", actualItems);

        cy.get(page.elements.manufacturerFilter).click();

        cy.contains(page.elements.filterLabel, manufacturer).click({
            force: true,
        });

        cy.get("@productCard").should("have.length", filteredItems);

        cy.get("@productCard").first().click();

        cy.get(page.elements.productDetailManufacturerLink).should(
            "contain",
            manufacturer
        );
    });
});
