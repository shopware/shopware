/**
 * @sw-package inventory
 */
import ProductTypeApiService from 'src/app/service/product-type.api.service';

describe('app/service/product-type.api.service.js', () => {
    it('should return the product types', async () => {
        const httpClientMock = {
            get: jest.fn(() =>
                Promise.resolve({
                    data: [
                        'digital',
                        'physical',
                    ],
                }),
            ),
        };

        const loginServiceMock = {
            getToken: jest.fn(() => Promise.resolve('token')),
        };

        const service = new ProductTypeApiService(httpClientMock, loginServiceMock);

        const result = await service.fetchProductTypes();

        expect(httpClientMock.get).toHaveBeenCalledWith('/_action/product/types', expect.any(Object));

        expect(result).toStrictEqual([
            'digital',
            'physical',
        ]);
    });
});
