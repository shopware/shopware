const Bottle = require('bottlejs');

const ModuleFactory = require('src/core/factory/module.factory');
const ComponentFactory = require('src/core/factory/component.factory');
const utils = require('src/core/service/util.service');
let TemplateFactory = require('src/core/factory/template.factory');
let ApplicationBootstrapper = require('src/core/application');

const container = new Bottle({
    strict: true
});
ApplicationBootstrapper = ApplicationBootstrapper.default;

const application = new ApplicationBootstrapper(container);
TemplateFactory = TemplateFactory.default;

const exposedInterface = {
    Module: {
        register: ModuleFactory.registerModule,
        getRegistry: ModuleFactory.getModuleRegistry,
        getRoutes: ModuleFactory.getModuleRoutes
    },
    Component: {
        register: ComponentFactory.register,
        extend: ComponentFactory.extend,
        override: ComponentFactory.override,
        build: ComponentFactory.build,
        getRegistry: ComponentFactory.getComponentRegistry,
        getTemplate: ComponentFactory.getComponentTemplate
    },
    Template: {
        register: TemplateFactory.registerComponentTemplate,
        extend: TemplateFactory.extendComponentTemplate,
        override: TemplateFactory.registerTemplateOverride,
        getRenderedTemplate: TemplateFactory.getRenderedTemplate,
        getRegistry: TemplateFactory.getTemplateRegistry,
        getOverrideRegistry: TemplateFactory.getTemplateRegistry,
        find: TemplateFactory.findCustomTemplate,
        findOverride: TemplateFactory.findCustomTemplate
    },
    Utils: utils.default,
    Application: application
};

module.exports = exposedInterface;
