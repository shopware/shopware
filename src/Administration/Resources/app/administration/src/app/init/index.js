/**
 * These types of initializers are called in the middle of the initialization process.
 * They are not allowed to depend on another initializers to suppress circular references.
 */
import initComponentHelper from 'src/app/init/component-helper.init';
import initHttpClient from 'src/app/init/http.init';
import initRepository from 'src/app/init/repository.init';
import initMixin from 'src/app/init/mixin.init';
import initCoreModules from 'src/app/init/modules.init';
import initLogin from 'src/app/init/login.init';
import initRouter from 'src/app/init/router.init';
import initFilter from 'src/app/init/filter.init';
import initDirectives from 'src/app/init/directive.init';
import initLocale from 'src/app/init/locale.init';
import initComponents from 'src/app/init/component.init';
import initSvgIcons from 'src/app/init/svg-icons.init';
import initShortcut from 'src/app/init/shortcut.init';
import initFilterFactory from 'src/app/init/filter-factory.init';
import initEntity from 'src/app/init/entity.init';

export default {
    coreMixin: initMixin,
    coreDirectives: initDirectives,
    coreFilter: initFilter,
    baseComponents: initComponents,
    svgIcons: initSvgIcons,
    coreModuleRoutes: initCoreModules,
    login: initLogin,
    router: initRouter,
    locale: initLocale,
    repositoryFactory: initRepository,
    shortcut: initShortcut,
    httpClient: initHttpClient,
    componentHelper: initComponentHelper,
    filterFactory: initFilterFactory,
    entity: initEntity,
};
