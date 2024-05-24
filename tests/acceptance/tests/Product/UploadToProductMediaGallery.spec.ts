import { test } from '@fixtures/AcceptanceTest';

test('Shop administrator should be able to upload an image to the product gallery within a product. @product' , async ({
    ShopAdmin,
    AdminProductDetail,
    UploadImage,
    SaveProduct,
    IdProvider,
}) => {

    const imageId = IdProvider.getIdPair().id;
    const imageName = `image-${ imageId }`;

    await ShopAdmin.goesTo(AdminProductDetail);
    await ShopAdmin.attemptsTo(UploadImage(imageName));
    await ShopAdmin.attemptsTo(SaveProduct());

    await ShopAdmin.expects(AdminProductDetail.productImage).toHaveCount(2);
    await ShopAdmin.expects(AdminProductDetail.productImage.first()).toHaveAttribute('alt', imageName);
    await ShopAdmin.expects(AdminProductDetail.productImage.nth(1)).toHaveAttribute('alt', imageName);
})
