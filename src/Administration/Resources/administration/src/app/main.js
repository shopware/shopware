/** Initializer */
import initializers from 'src/app/init';

/** Services */
import MenuService from 'src/app/service/menu.service';
import LoginService from 'src/core/service/login.service';
import apiServices from 'src/core/service/api';
import JsonApiParser from 'src/core/service/jsonapi-parser.service';

/** Import global styles */
import 'src/app/assets/less/all.less';

const application = Shopware.Application;

// Add initializers
Object.keys(initializers).forEach((key) => {
    const initializer = initializers[key];
    application.addInitializer(key, initializer);
});

// Add service providers
application
    .addServiceProvider('menuService', () => {
        const factoryContainer = application.getContainer('factory');
        return MenuService(factoryContainer.module);
    })
    .addServiceProvider('loginService', () => {
        const initContainer = application.getContainer('init');
        return LoginService(initContainer.httpClient);
    })
    .addServiceProvider('jsonApiParserService', () => {
        return JsonApiParser;
    });

// Add api service providers
Object.keys(apiServices).forEach((key) => {
    const ServiceFactoryClass = apiServices[key];

    application.addServiceProvider(key, (container) => {
        const initContainer = application.getContainer('init');
        return new ServiceFactoryClass(initContainer.httpClient, container.loginService);
    });
});

// When we're working with the hot module replacement server we wanna start up the application right away, we're
// ignoring the code coverage for it cause we'll never hit the hot module reloading mode with unit tests.

/* istanbul ignore if */
if (module.hot) {
    application.start();
}
