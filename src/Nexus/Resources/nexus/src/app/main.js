import ShopwareApplication from 'src/core/application';

// import initCSRF from 'src/app/init/csrf.init';
import initHttpClient from 'src/app/init/http.init';
import initAppState from 'src/app/init/state.init';
import initCoreModules from 'src/app/init/modules.init';
import initView from 'src/app/init/view.init';
import initRouter from 'src/app/init/router.init';
import initProvider from 'src/app/init/provider.init';

const application = new ShopwareApplication();

application
// .addInitializer(initializeCSRFToken)
    .addInitializer(initHttpClient)
    .addInitializer(initAppState)
    .addInitializer(initCoreModules)
    .addInitializer(initView)
    .addInitializer(initRouter)
    .addInitializer(initProvider)
    .start();

export default application;
