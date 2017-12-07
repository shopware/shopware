const Bottle = require('bottlejs');

const utils = require('src/core/service/util.service');
let ModuleFactory = require('src/core/factory/module.factory');
let ComponentFactory = require('src/core/factory/component.factory');
let TemplateFactory = require('src/core/factory/template.factory');
let ApplicationBootstrapper = require('src/core/application');

const container = new Bottle({
    strict: true
});
ApplicationBootstrapper = ApplicationBootstrapper.default;

const application = new ApplicationBootstrapper(container);
TemplateFactory = TemplateFactory.default;
ComponentFactory = ComponentFactory.default;
ModuleFactory = ModuleFactory.default;

application
    .addFactory('component', () => {
        return ComponentFactory;
    })
    .addFactory('template', () => {
        return TemplateFactory;
    })
    .addFactory('module', () => {
        return ModuleFactory;
    });

const exposedInterface = {
    Module: {
        register: ModuleFactory.registerModule
    },
    Component: {
        register: ComponentFactory.register,
        extend: ComponentFactory.extend,
        override: ComponentFactory.override,
        build: ComponentFactory.build,
        getTemplate: ComponentFactory.getComponentTemplate
    },
    Template: {
        register: TemplateFactory.registerComponentTemplate,
        extend: TemplateFactory.extendComponentTemplate,
        override: TemplateFactory.registerTemplateOverride,
        getRenderedTemplate: TemplateFactory.getRenderedTemplate,
        find: TemplateFactory.findCustomTemplate,
        findOverride: TemplateFactory.findCustomTemplate
    },
    Utils: utils.default,
    Application: application
};

module.exports = exposedInterface;
