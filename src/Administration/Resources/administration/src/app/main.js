/** Initializer */
import initContext from 'src/app/init/context.init';
import initHttpClient from 'src/app/init/http.init';
import initCoreModules from 'src/app/init/modules.init';
import initView from 'src/app/init/view.init';
import initRouter from 'src/app/init/router.init';

/** Services */
import MenuService from 'src/app/service/menu.service';
import apiServices from 'src/core/service/api';

/** Import global styles */
import 'src/app/assets/less/all.less';

const application = Shopware.Application;

application
    .addInitializer('contextService', initContext)
    .addInitializer('httpClient', initHttpClient)
    .addInitializer('coreModuleRoutes', initCoreModules)
    .addInitializer('view', initView)
    .addInitializer('router', initRouter)
    .addServiceProvider('menuService', () => {
        return MenuService();
    });

// Loop through the api services and register them as service providers in the application
apiServices.forEach((service) => {
    const ServiceFactoryClass = service.provider;
    const name = service.name;

    application.addServiceProvider(name, () => {
        const initContainer = application.$container.container.init;
        return new ServiceFactoryClass(initContainer.httpClient);
    });
});

// When we're working with the hot module replacement server we wanna start up the application right away, we're
// ignoring the code coverage for it cause we'll never hit the hot module reloading mode with unit tests.

/* istanbul ignore if */
if (module.hot) {
    application.start();
}
