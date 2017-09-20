import EventEmitter from 'src/core/factory/event-emitter.factory';
import ProductService from 'src/core/service/api/product/product.service';
import ProductManufacturerService from 'src/core/service/api/product_manufacturer/product_manufacturer.service';
import TaxService from 'src/core/service/api/tax/tax.service';
import CategoryService from 'src/core/service/api/category/category.service';
import MediaService from 'src/core/service/api/media/media.service';
import LoginService from 'src/core/service/api/login/login.service';
import ConvenientProductService from 'src/core/service/convenient/product.convenient.service';

import MenuService from 'src/app/service/menu.service';

export default function initializeProviders(app, configuration, done) {
    const httpClient = configuration.httpClient;
    const eventSystem = configuration.eventSystem;
    const stateContainer = configuration.stateContainer;
    const applicationState = configuration.applicationState;

    app.addProvider('httpClient', httpClient)
        .addProvider('eventSystem', eventSystem)
        .addProvider('eventEmitter', EventEmitter(eventSystem))
        .addProvider('stateContainer', stateContainer)
        .addProvider('productService', ProductService(httpClient))
        .addProvider('productManufacturerService', ProductManufacturerService(httpClient))
        .addProvider('taxService', TaxService(httpClient))
        .addProvider('categoryService', CategoryService(httpClient))
        .addProvider('mediaService', MediaService(httpClient))
        .addProvider('loginService', LoginService(httpClient))
        .addProvider('applicationState', applicationState)
        .addProvider('menuService', MenuService)
        .addProvider('convenientProductService',
            ConvenientProductService(
                ProductService(httpClient),
                ProductManufacturerService(httpClient),
                MediaService(httpClient)
            )
        );

    done(configuration);
}
