import initContext from 'src/app/init/context.init';
import initHttpClient from 'src/app/init/http.init';
import initCoreModules from 'src/app/init/modules.init';
import initView from 'src/app/init/view.init';
import initRouter from 'src/app/init/router.init';
import initEntity from 'src/app/init/entity.init';

export default {
    contextService: initContext,
    httpClient: initHttpClient,
    coreModuleRoutes: initCoreModules,
    view: initView,
    router: initRouter,
    entity: initEntity
};
