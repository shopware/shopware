/** Application Bootstrapper */
import { Application } from 'src/core/shopware';

/** Initializer */
import initializers from 'src/app/init';

/** Services */
import MenuService from 'src/app/service/menu.service';
import LoginService from 'src/core/service/login.service';
import EntityMappingService from 'src/core/service/entity-mapping.service';
import JsonApiParser from 'src/core/service/jsonapi-parser.service';
import ValidationService from 'src/core/service/validation.service';
import RuleConditionService from 'src/app/service/rule-condition.service';
import ProductStreamConditionService from 'src/app/service/product-stream-condition.service';
import StateStyleService from 'src/app/service/state-style.service';
import CustomFieldService from 'src/app/service/custom-field.service';
import SearchTypeService from 'src/app/service/search-type.service';
import ShortcutService from 'src/app/service/shortcut.service';
import LocaleToLanguageService from 'src/app/service/locale-to-language.service';
import addPluginUpdatesListener from 'src/core/service/plugin-updates-listener.service';
import addShopwareUpdatesListener from 'src/core/service/shopware-updates-listener.service';
import 'src/app/decorator';

import RouterFactory from 'src/core/factory/router.factory';
import coreRoutes from 'src/app/route';
import VueRouter from 'vue-router';

/** Import global styles */
import 'src/app/assets/scss/all.scss';

const factoryContainer = Application.getContainer('factory');

// Add initializers
Object.keys(initializers).forEach((key) => {
    const initializer = initializers[key];
    Application.addInitializer(key, initializer);
});

Application.addInitializerDecorator('router', (originalService) => {
    const initContainer = Application.getContainer('init');
    const loginService = Application.getContainer('service').loginService;
    const moduleFactoy = factoryContainer.module;

    const context = initContainer.context;

    if (!context.firstRunWizard) {
        return originalService;
    }

    const firstRunWizard = moduleFactoy.getModuleRegistry().get('sw-first-run-wizard');
    const moduleRoutes = [];
    firstRunWizard.routes.forEach((route) => {
        if (route.routeKey !== 'index') {
            return;
        }

        moduleRoutes.push(route);
    });

    const factory = RouterFactory(VueRouter, initContainer.view, {
        getModuleRegistry() {
            const map = new Map();
            map.set('sw-first-run-wizard', firstRunWizard);
            return new Map();
        }
    }, loginService);

    factory.addRoutes(coreRoutes);
    factory.addModuleRoutes(moduleRoutes);
    factory.createRouterInstance();

    return factory;
});

// Add service providers
Application
    .addServiceProvider('menuService', () => {
        return MenuService(factoryContainer.module);
    })
    .addServiceProvider('loginService', () => {
        const serviceContainer = Application.getContainer('service');
        const initContainer = Application.getContainer('init');
        const loginService = LoginService(initContainer.httpClient, serviceContainer.context);

        addPluginUpdatesListener(loginService, serviceContainer);
        addShopwareUpdatesListener(loginService, serviceContainer);

        return loginService;
    })
    .addServiceProvider('jsonApiParserService', () => {
        return JsonApiParser;
    })
    .addServiceProvider('validationService', () => {
        return ValidationService;
    })
    .addServiceProvider('ruleConditionDataProviderService', () => {
        return RuleConditionService();
    })
    .addServiceProvider('productStreamConditionService', () => {
        return ProductStreamConditionService();
    })
    .addServiceProvider('customFieldDataProviderService', () => {
        return CustomFieldService();
    })
    .addServiceProvider('stateStyleDataProviderService', () => {
        return StateStyleService();
    })
    .addServiceProvider('searchTypeService', () => {
        return SearchTypeService();
    })
    .addServiceProvider('localeToLanguageService', () => {
        return LocaleToLanguageService();
    })
    .addServiceProvider('entityMappingService', () => {
        return EntityMappingService;
    })
    .addServiceProvider('shortcutService', () => {
        return ShortcutService(factoryContainer.shortcut);
    });
