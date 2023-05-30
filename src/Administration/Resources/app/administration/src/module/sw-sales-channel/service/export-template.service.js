/**
 * @package sales-channel
 */

const { Application } = Shopware;

Application.addServiceProvider('exportTemplateService', () => {
    return {
        registerProductExportTemplate,
        getProductExportTemplateByName,
        getProductExportTemplateRegistry,
    };
});

const templateRegistry = {};

function registerProductExportTemplate(template) {
    templateRegistry[template.name] = template;

    return true;
}

function getProductExportTemplateByName(name) {
    return templateRegistry[name];
}

function getProductExportTemplateRegistry() {
    return templateRegistry;
}
