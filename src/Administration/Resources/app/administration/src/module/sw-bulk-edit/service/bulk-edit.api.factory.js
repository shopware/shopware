import BulkEditProductHandler from './handler/bulk-edit-product.handler';
import BulkEditOrderHandler from './handler/bulk-edit-order.handler';

/**
 * @class
 */
class BulkEditApiFactory {
    constructor() {
        this.handlers = {
            product: () => new BulkEditProductHandler(),
            order: () => new BulkEditOrderHandler(),
            // TODO: add handlers for customer
        };
    }

    getHandler(module) {
        if (!this.handlers[module]) {
            throw Error(`Bulk Edit Handler not found for ${module} module`);
        }

        // Lazy load the module handler
        return this.handlers[module]();
    }
}

export default BulkEditApiFactory;
