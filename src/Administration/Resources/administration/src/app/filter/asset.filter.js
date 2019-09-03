const { Application, Filter } = Shopware;

Filter.register('asset', (value) => {
    if (!value) {
        return '';
    }

    const serviceContainer = Application.getContainer('service');
    const context = serviceContainer.context;

    return `${context.assetsPath}${value}`;
});
