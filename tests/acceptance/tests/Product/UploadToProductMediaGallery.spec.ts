import { test } from '@fixtures/AcceptanceTest';

test('Shop administrator should be able to upload an image to the product gallery within a product. @product' , async ({
    shopAdmin,
    adminProductDetailPage,
    UploadImage,
    SaveProduct,
    idProvider,
}) => {

    const imageId = idProvider.getIdPair().id;
    const imageName = `image-${imageId}`;

    await shopAdmin.goesTo(adminProductDetailPage);
    await shopAdmin.attemptsTo(UploadImage(imageId, imageName));
    await shopAdmin.attemptsTo(SaveProduct());

    //Assertions
    await shopAdmin.expects(adminProductDetailPage.productImage).toHaveCount(2);
    await shopAdmin.expects(adminProductDetailPage.productImage.first()).toHaveAttribute('alt', imageName);
    await shopAdmin.expects(adminProductDetailPage.productImage.nth(1)).toHaveAttribute('alt', imageName);
})
