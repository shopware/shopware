import { test as base } from '@playwright/test';
import { expect } from '@fixtures/AcceptanceTest';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import { components } from '@shopware/api-client/admin-api-types';

export const DigitalProductData = base.extend<FixtureTypes>({
    digitalProductData: async ({ idProvider, adminApiContext, productData }, use) => {

        // Create new Media resource in the default folder for digital product media
        const newMediaResource = await adminApiContext.post('./media?_response', {
            data: {
                private: false,
            }, 
        });

        await expect(newMediaResource.ok()).toBeTruthy();
        const { data: newMediaValue } = (await newMediaResource.json()) as { data: components['schemas']['Media'] };
        const newMediaId = newMediaValue.id;

        // Create media upload (simple textfile with content "Test123")
        const filename = 'testfile_' + idProvider.getUniqueName();
        const fileContent = 'This is a test file to test digital product download';
        const newMediaUpload = await adminApiContext.post(`./_action/media/${newMediaId}/upload?extension=txt&fileName=${filename}&_response`, {
            headers:{
                'content-type': 'application/octet-stream',
            },
            data: fileContent,
        }); 
        await expect(newMediaUpload.ok()).toBeTruthy();

        const productDownloadResponse = await adminApiContext.post(`./product-download?_response`, {
            data: {
                productId: productData.id,
                mediaId: newMediaId,
            },
        });
        await expect(productDownloadResponse.ok()).toBeTruthy();
        const { data: productDownload } = await productDownloadResponse.json();

        const returnData = {
            product: productData,
            fileContent,
        }  
        // Use product data in the test
        await use(returnData);

        // List orders with product to delete them
        const orderSearchResponse = await adminApiContext.post('./search/order', {
            data: {
                limit: 10,
                filter: [
                    {
                        type: 'equals',
                        field: 'lineItems.productId',
                        value: productData.id,
                    },
                ],
            },
        });

        await expect(orderSearchResponse.ok()).toBeTruthy();
        const { data: ordersWithDigitalProduct } = (await orderSearchResponse.json()) as { data: components['schemas']['Order'][]};

        // Delete Orders using the digital product, to be able to delete the uploaded media file
        for (const order of ordersWithDigitalProduct) { 
            const deleteOrderResponse = await adminApiContext.delete(`./order/${order.id}`);
            await expect(deleteOrderResponse.ok()).toBeTruthy();
        } 

        // Unlink the media file from the product by deleting the product-download
        const unlinkMediaResponse = await adminApiContext.delete(`./product-download/${productDownload.id}`);
        await expect(unlinkMediaResponse.ok()).toBeTruthy();

        // Delete media after the test is done
        const cleanupMediaResponse = await adminApiContext.delete(`./media/${newMediaId}`);
        await expect(cleanupMediaResponse.ok()).toBeTruthy();

        // Delete Product
        // This is done in Product.data.ts
    },
});
