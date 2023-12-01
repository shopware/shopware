import type { Page, Locator } from '@playwright/test';
import type { PageObject } from '@fixtures/PageObject';

export class AdminProductDetailPage implements PageObject {
    /**
     * Tabs
     */
    public readonly variantsTabLink: Locator;

    /**
     * Variants Generation
     */
    public readonly generateVariantsButton: Locator;
    public readonly variantsModal: Locator;
    public readonly variantsModalHeadline: Locator;
    public readonly variantsNextButton: Locator;
    public readonly variantsSaveButton: Locator;

    /**
     * Property Selection
     */
    public readonly propertyGroupColor: Locator;
    public readonly propertyGroupSize: Locator;

    public readonly propertyOptionGrid: Locator;
    public readonly propertyOptionColorBlue: Locator;
    public readonly propertyOptionColorRed: Locator;
    public readonly propertyOptionColorGreen: Locator;
    public readonly propertyOptionSizeSmall: Locator;
    public readonly propertyOptionSizeMedium: Locator;
    public readonly propertyOptionSizeLarge: Locator;

    constructor(public readonly page: Page, public readonly product) {
        this.variantsTabLink = page.getByRole('link', { name: 'Variants' });

        this.generateVariantsButton = page.getByRole('button', { name: 'Generate variants' });
        this.variantsModal = page.getByRole('dialog', { name: 'Generate variants' });
        this.variantsModalHeadline = this.variantsModal.getByRole('heading', { name: 'Generate variants' });
        this.variantsNextButton = this.variantsModal.getByRole('button', { name: 'Next' });
        this.variantsSaveButton = this.variantsModal.getByRole('button', { name: 'Save variants' });

        this.propertyGroupColor = this.variantsModal.getByText('Color').first();
        this.propertyGroupSize = this.variantsModal.getByText('Size').first();

        this.propertyOptionGrid = this.variantsModal.locator('.sw-property-search__tree-selection__option_grid');
        this.propertyOptionColorBlue = this.propertyOptionGrid.getByLabel('Blue');
        this.propertyOptionColorRed = this.propertyOptionGrid.getByLabel('Red');
        this.propertyOptionColorGreen = this.propertyOptionGrid.getByLabel('Green');
        this.propertyOptionSizeSmall = this.propertyOptionGrid.getByLabel('Small');
        this.propertyOptionSizeMedium = this.propertyOptionGrid.getByLabel('Medium');
        this.propertyOptionSizeLarge = this.propertyOptionGrid.getByLabel('Large');
    }

    async goTo() {
        await this.page.goto(`#/sw/product/detail/${this.product.id}/base`);
    }
}
