import type { Page, Locator } from '@playwright/test';
import type { PageObject } from '@fixtures/PageObject';

export class AdminProductDetailPage implements PageObject {

    /**
     * Save interactions
     */
    public readonly savePhysicalProductButton: Locator;
    public readonly saveButtonLoadingSpinner: Locator;
    public readonly saveButtonCheckMark: Locator;

    /**
     * Media Upload interactions
     */
    public readonly uploadMediaButton: Locator;
    public readonly coverImage: Locator;
    public readonly productImage: Locator;

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

    public readonly productData;


    constructor(public readonly page: Page, productData) {

        this.productData = productData;

        this.savePhysicalProductButton = page.getByRole('button', { name: 'Save' });
        this.saveButtonCheckMark = page.locator('.icon--regular-checkmark-xs')
        this.saveButtonLoadingSpinner = page.locator('sw-loader');

        this.uploadMediaButton = page.getByRole('button', { name: 'Upload file' });
        this.coverImage = page.locator('.sw-product-media-form__cover-image');
        this.productImage = page.locator('.sw-media-preview-v2__item');

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
        await this.page.goto(`#/sw/product/detail/${this.productData.id}/base`);
    }

    getProductId(){
        return this.productData.id;
    }

}
