import utils from './../service/util.service';

export default {
    create: createProxy
};

class DataProxy {
    constructor(data) {
        this.originalData = data;

        if (data.constructor === Array) {
            this.processedData = [...data];
        } else if (data.constructor === Object) {
            this.processedData = { ...data };
        }

        this.versions = [];
    }

    get data() {
        return this.processedData;
    }

    set data(data) {
        // Save the old version for version control.
        if (this.originalData.constructor === Array) {
            this.versions.push([...this.originalData]);
        } else if (this.originalData.constructor === Object) {
            this.versions.push({ ...this.originalData });
        }

        // Set the changed data as new original data set to track new changes.
        this.originalData = data;

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
        if (data.constructor === Array) {
            this.processedData = [...data];
        } else if (data.constructor === Object) {
            Object.apply(this.processedData, data);
        }
    }

    get changeSet() {
        return utils.compareObjects(this.originalData, this.processedData);
    }
}

function createProxy(data) {
    return new DataProxy(data);
}
