import utils from 'src/core/service/util.service';

/**
 * The data proxy class is used for generating change sets based on the original data provided in the constructor and
 * the changed data. We're using partial updates for changes to existing entities.
 *
 * @class
 */
class DataProxy {
    /**
     * Creates a new instance of the data proxy
     *
     * @constructor
     * @param {Object} data Any kind of data
     */
    constructor(data) {
        this.originalData = this.deepCopy(data);
        this.processedData = this.deepCopy(data);

        this.versions = [];
    }

    /**
     * Returns the processed data
     *
     * @type {Object}
     */
    get data() {
        return this.processedData;
    }

    /**
     * Setter for data property. The setter is versioning the data to track the changes to the data property.
     *
     * @type {Object}
     */
    set data(data) {
        // Save the old version for version control.
        this.versions.push(this.deepCopy(this.originalData));

        // Set the changed data as new original data set to track new changes.
        this.originalData = this.deepCopy(data);

        /**
         * ToDo: Add support for updatedAt!
         *
         * The date will be added to the changeSet after saving the product.
         * This should not be send to the server every time.
         * Also the API right now requests this as datetime format but serves it as an object.
         */
        if (data.updatedAt) {
            delete data.updatedAt;
        }

        // Update the exposed data object. The original reference to the data object has to be kept for the data binding.
        this.processedData = Object.assign(this.processedData, data);
    }

    /**
     * Getter for the change set which returns the changes between the original data and the processed data
     *
     * @type {Object}
     */
    get changeSet() {
        return utils.getObjectChangeSet(this.originalData, this.processedData);
    }

    /**
     * Returns a copy of an object
     *
     * @param {Object} data
     * @returns {Object}
     */
    static deepCopy(data) {
        return JSON.parse(JSON.stringify(data));
    }
}

export default DataProxy;
