import ContextFactory from 'src/core/factory/context.factory';

/**
 * Initializes the context of application. The context contains information about the installation path,
 * assets path and api path.
 */
export default function initializeContext(container) {
    return ContextFactory(container.context);
}
