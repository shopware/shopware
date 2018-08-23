/** Application Bootstrapper */
import { Application } from 'src/core/shopware';

/** Initializer */
import initializers from 'src/app/init';

/** Services */
import MenuService from 'src/app/service/menu.service';
import LoginService from 'src/core/service/login.service';
import apiServices from 'src/core/service/api';
import JsonApiParser from 'src/core/service/jsonapi-parser.service';
import ValidationService from 'src/core/service/validation.service';

/** Import global styles */
import 'src/app/assets/less/all.less';

// Add initializers
Object.keys(initializers).forEach((key) => {
    const initializer = initializers[key];
    Application.addInitializer(key, initializer);
});

// Add service providers
Application
    .addServiceProvider('menuService', () => {
        const factoryContainer = Application.getContainer('factory');
        return MenuService(factoryContainer.module);
    })
    .addServiceProvider('loginService', () => {
        const initContainer = Application.getContainer('init');
        return LoginService(initContainer.httpClient);
    })
    .addServiceProvider('jsonApiParserService', () => {
        return JsonApiParser;
    })
    .addServiceProvider('validationService', () => {
        return ValidationService;
    });

// Add api service providers
Object.keys(apiServices).forEach((key) => {
    const ServiceFactoryClass = apiServices[key];

    Application.addServiceProvider(key, (container) => {
        const initContainer = Application.getContainer('init');
        return new ServiceFactoryClass(initContainer.httpClient, container.loginService);
    });
});
