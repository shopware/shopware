import { test } from '@fixtures/AcceptanceTest';
import { satisfies } from 'compare-versions';

test('As a merchant, I want to perform bulk edits on products information.', { tag: '@Product' }, async ({
    TestDataService,
    ShopAdmin,
    AdminProductListing,
    AdminProductDetail,
    BulkEditProducts,
    DefaultSalesChannel,
    IdProvider,
    InstanceMeta,
}) => {

    test.slow();

    const originalStock = 200;
    const originalRestockTime = 10;
    const tagUuid = IdProvider.getIdPair().uuid;
    const originalTag = await TestDataService.createTag('Tag1-' + tagUuid);
    const addedTag = await TestDataService.createTag('Tag2-' + tagUuid);
    const changedProduct1 = await TestDataService.createBasicProduct({ stock: originalStock, restockTime: originalRestockTime, tags: [{ id: originalTag.id }], visibilities: [{ salesChannelId: DefaultSalesChannel.salesChannel.id, visibility: 30 }] });
    const changedProduct2 = await TestDataService.createBasicProduct({ stock: originalStock, restockTime: originalRestockTime, tags: [{ id: originalTag.id }], visibilities: [{ salesChannelId: DefaultSalesChannel.salesChannel.id, visibility: 30 }] });
    const unchangedProduct = await TestDataService.createBasicProduct({ stock: originalStock, restockTime: originalRestockTime, tags: [{ id: originalTag.id }], visibilities: [{ salesChannelId: DefaultSalesChannel.salesChannel.id, visibility: 30 }] });
    const originalProductPrice = unchangedProduct.price[0].gross.toString();
    const changedProducts = [changedProduct1, changedProduct2];
    const changedManufacturer = await TestDataService.createBasicManufacturer();

    let changedReleaseDate: string;
    // eslint-disable-next-line playwright/no-conditional-in-test
    satisfies(InstanceMeta.version, '<6.7') 
        ? changedReleaseDate = '31/12/2024, 23:59' 
        : changedReleaseDate = '2025-01-01 00:01';
        
    const changes = {
        'grossPrice': { value: '99.99', method: '' },
        'active': { value: 'false', method: '' },
        'manufacturer': { value: changedManufacturer.name, method: '' },
        'releaseDate': { value: changedReleaseDate, method: '' },
        'stock': { value: '400', method: 'Overwrite' },
        'restockTime': { value: '', method: 'Clear' },
        'tags': { value: addedTag.name, method: 'Add' },
        'saleschannel': { value: DefaultSalesChannel.salesChannel.name, method: 'Remove' },
    };

    await test.step('Bulk edit two products.', async () => {
        await ShopAdmin.goesTo(AdminProductListing.url([changedProduct1.name, changedProduct2.name, unchangedProduct.name]));
        await ShopAdmin.attemptsTo(BulkEditProducts(changedProducts, changes));
    });

    await test.step('Confirm that two products have changes and one has no changes.', async () => {

        // Verify the changes for the bulk edited products
        for (const product of changedProducts) {
            await ShopAdmin.goesTo(AdminProductDetail.url(product.id));
            await ShopAdmin.expects(AdminProductDetail.priceGrossInput).toHaveValue(changes['grossPrice'].value);
            await ShopAdmin.expects(AdminProductDetail.activeForAllSalesChannelsToggle).not.toBeChecked();
            await ShopAdmin.expects(AdminProductDetail.manufacturerDropdownText).toHaveText(changes['manufacturer'].value);
            await ShopAdmin.expects(AdminProductDetail.releaseDateInput).toHaveValue(changes['releaseDate'].value);
            await ShopAdmin.expects(AdminProductDetail.stockInput).toHaveValue(changes['stock'].value);
            await ShopAdmin.expects(AdminProductDetail.restockTimeInput).toHaveValue('');
            await ShopAdmin.expects(AdminProductDetail.tagsInput).toContainText(originalTag.name);
            await ShopAdmin.expects(AdminProductDetail.tagsInput).toContainText(addedTag.name);
            await ShopAdmin.expects(AdminProductDetail.saleChannelsInput).toHaveText('');
        }

        // Verify that the product that was not part of the bulk edit has not changed
        await ShopAdmin.goesTo(AdminProductDetail.url(unchangedProduct.id));
        await ShopAdmin.expects(AdminProductDetail.priceGrossInput).toHaveValue(originalProductPrice);
        await ShopAdmin.expects(AdminProductDetail.activeForAllSalesChannelsToggle).toBeChecked();
        await ShopAdmin.expects(AdminProductDetail.manufacturerDropdownText).toHaveText('Enter product manufacturer...');

        // Verify the release date input value
        // eslint-disable-next-line playwright/no-conditional-in-test
        if (satisfies(InstanceMeta.version, '<6.7')) {
            await ShopAdmin.expects(AdminProductDetail.releaseDateInput).toHaveValue('');
        } else {
            const todayDate = new Date().toISOString().slice(0, 10); // "YYYY-MM-DD"
            const receivedDate = (await AdminProductDetail.releaseDateInput.inputValue()).split(' ')[0];
            await ShopAdmin.expects(receivedDate).toContain(todayDate);
        }

        await ShopAdmin.expects(AdminProductDetail.stockInput).toHaveValue(originalStock.toString());
        await ShopAdmin.expects(AdminProductDetail.restockTimeInput).toHaveValue(originalRestockTime.toString());
        await ShopAdmin.expects(AdminProductDetail.tagsInput).toContainText(originalTag.name);
        await ShopAdmin.expects(AdminProductDetail.tagsInput).not.toContainText(addedTag.name);
        await ShopAdmin.expects(AdminProductDetail.saleChannelsInput).toContainText(DefaultSalesChannel.salesChannel.name);
    });
});
