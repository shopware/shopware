const Bottle = require('bottlejs');

const ModuleFactory = require('src/core/factory/module.factory');
const ComponentFactory = require('src/core/factory/component.factory');
const utils = require('src/core/service/util.service');
const TemplateFactory = require('src/core/factory/template.factory');
const ViewFactory = require('src/core/factory/view.factory');
const RouterFactory = require('src/core/factory/router.factory');
let ApplicationBootstrapper = require('src/core/application');

const container = new Bottle({
    strict: true
});
ApplicationBootstrapper = ApplicationBootstrapper.default;

const application = new ApplicationBootstrapper(container);

module.exports = {
    ModuleFactory,
    ComponentFactory,
    TemplateFactory,
    ViewFactory,
    RouterFactory,
    utils,
    Application: application,
    Container: container
};
