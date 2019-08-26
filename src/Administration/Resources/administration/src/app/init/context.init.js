const ContextFactory = Shopware._private.ContextFactory;

/**
 * Initializes the context of application. The context contains information about the installation path,
 * assets path and api path.
 */
export default function initializeContext(container) {
    const context = ContextFactory(container.context);

    this.addServiceProvider('context', () => {
        return context;
    });

    return context;
}
