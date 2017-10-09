import ContextFactory from 'src/core/factory/context.factory';

/**
 * Initializes the context of application. The context contains information about the installation path,
 * assets path and api path.
 *
 * @param {ShopwareApplication} app
 * @param {Object} configuration
 * @param {Function} done
 * @param {Object} context
 */
export default function initializeContext(app, configuration, done, context) {
    const shopwareContext = new ContextFactory(context);
    configuration.context = shopwareContext;

    done(configuration);
}
