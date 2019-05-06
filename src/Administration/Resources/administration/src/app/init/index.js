import initContext from 'src/app/init/context.init';
import initHttpClient from 'src/app/init/http.init';
import initEntity from 'src/app/init/entity.init';
import initRepository from 'src/app/init/repository.init';
import initState from 'src/app/init/state.init';
import initMixin from 'src/app/init/mixin.init';
import initCoreModules from 'src/app/init/modules.init';
import initView from 'src/app/init/view.init';
import initRouter from 'src/app/init/router.init';
import initFilter from 'src/app/init/filter.init';
import initDirectives from 'src/app/init/directive.init';
import initLocale from 'src/app/init/locale.init';
import initApiServices from 'src/app/init/api-services.init';
import initComponents from 'src/app/init/component.init';
import initWorker from 'src/app/init/worker.init';
import initSvgIcons from 'src/app/init/svg-icons.init';
import initUserContext from 'src/app/init/user-information.init';
import initConfig from 'src/app/init/config.init';

export default {
    contextService: initContext,
    httpClient: initHttpClient,
    apiServices: initApiServices,
    coreState: initState,
    coreMixin: initMixin,
    coreDirectives: initDirectives,
    coreFilter: initFilter,
    baseComponents: initComponents,
    svgIcons: initSvgIcons,
    coreModuleRoutes: initCoreModules,
    view: initView,
    router: initRouter,
    entity: initEntity,
    locale: initLocale,
    config: initConfig,
    worker: initWorker,
    userInfo: initUserContext,
    repositoryFactory: initRepository
};
