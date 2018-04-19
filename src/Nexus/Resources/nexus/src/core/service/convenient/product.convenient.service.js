import Rx from 'rxjs/Rx';

export default function ConvenientProductService(productService, manufacturerService) {
    return {
        readProductById
    };

    function readProductById(id) {
        if (!id) {
            return Promise.reject(new Error('"id" argument needs to be provided'));
        }

        return Rx.Observable.fromPromise(
            productService.readProductById(id)
        ).flatMap((product) => {
            return Rx.Observable.forkJoin(
                Rx.Observable.of(product),
                manufacturerService.readManufacturerById(product.supplierId)
            ).map((data) => {
                const combinedProduct = data[0];
                const manufacturer = data[1];

                combinedProduct.manufacturer = manufacturer;
                return combinedProduct;
            });
        });
    }
}
