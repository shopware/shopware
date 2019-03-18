import { Application, State } from 'src/core/shopware';
import EntityStore from 'src/core/data/EntityStore';
import { deepCopyObject } from 'src/core/service/utils/object.utils';
import EventEmitter from 'events';
import { md5 } from 'src/core/service/utils/format.utils';

export default class VariantsGenerator extends EventEmitter {
    constructor(product) {
        super();

        this.product = product;

        // set dependencies
        this.syncService = Application.getContainer('service').syncService;
        this.EntityStore = EntityStore;
        this.State = State;
        this.httpClient = this.syncService.httpClient;

        // local data
        this.configuratorStore = this.product.getAssociation('configurators');
        this.languageId = null;
        this.productStore = this.State.getStore('product');
        this.languageStore = this.State.getStore('language');
    }

    // functions
    createNewVariants(forceGenerating) {
        return new Promise((resolve, reject) => {
            const grouped = this.groupTheOptions(this.configuratorStore);

            // When nothing is selected, delete everything
            if (grouped.length <= 0) {
                this.loadExisting(this.product.id).then((variantsOnServer) => {
                    const deleteArray = Object.keys(variantsOnServer).map((id) => { return { id }; });
                    this.emit('maxProgressChange', { type: 'delete', progress: deleteArray.length });
                    this.processQueue('delete', deleteArray, 0, 10, resolve);
                });
                return;
            }

            // check for large request over 10 000 variants
            const numberOfVariants = grouped.map((group) => group.length).reduce((curr, length) => curr * length);
            if (!forceGenerating && numberOfVariants >= 10000) {
                this.emit('warning', numberOfVariants);
                reject(new Error('Warning fired'));
                return;
            }

            // create permutations of variants
            const permutations = this.buildCombinations(grouped);

            this.loadExisting(this.product.id).then((variantsOnServer) => {
                // filter deletable and creatable variations
                this.filterVariations(permutations, variantsOnServer).then((queues) => {
                    new Promise((resolveDelete) => {
                        // notify view to refresh progrss
                        this.emit('maxProgressChange', { type: 'delete', progress: queues.deleteQueue.length });

                        // create mapping for api call
                        const mapped = queues.deleteQueue.map((id) => {
                            return { id };
                        });

                        // send api calls for delete
                        this.processQueue('delete', mapped, 0, 10, resolveDelete);
                    }).then(() => {
                        // notify view to refresh progress
                        this.emit('maxProgressChange', { type: 'upsert', progress: queues.createQueue.length });

                        // send api calls for create
                        this.processQueue('upsert', queues.createQueue, 0, 10, resolve);
                    });
                });
            });
        });
    }

    filterVariations(newVariations, variationOnServer) {
        return new Promise((resolve) => {
            const createQueue = [];

            /*
             * {
             *      hash1: variantId
             *      hash2: variantId2
             *      hash3: variantId..
             * }
             *
             */
            const hashed = {};
            // eslint-disable-next-line no-restricted-syntax
            for (const [key, options] of Object.entries(variationOnServer)) {
                const hash = md5(JSON.stringify(options.sort()));
                hashed[hash] = key;
            }

            let deleteQueue = deepCopyObject(hashed);

            const newVariationsSorted = newVariations.map((variation) => variation.sort());

            // notify page that the generation starts now
            this.emit('maxProgressChange', { type: 'calc', progress: newVariationsSorted.length });

            newVariationsSorted.forEach((variation) => {
                const hash = md5(JSON.stringify(variation));
                const exist = hashed[hash];

                if (exist !== undefined) {
                    delete deleteQueue[hash];
                } else {
                    const variations = variation.map((optionId) => {
                        return { id: optionId };
                    });

                    // todo calculate price with surcharges
                    // todo consider product.priceRules (store of prices)
                    // Add to create list
                    createQueue.push({
                        parentId: this.product.id,
                        variations: variations,
                        stock: this.product.stock,
                        price: { gross: 200, net: 100, linked: false }
                    });
                }
            });

            deleteQueue = Object.values(deleteQueue);

            resolve({ deleteQueue, createQueue });
        });
    }

    loadExisting(id) {
        return this.httpClient.get(
            `/_action/product/${id}/combinations`,
            { headers: this.syncService.getBasicHeaders() }
        ).then((response) => {
            return response.data;
        });
    }

    groupTheOptions(configurators) {
        const groupedData = Object.values(configurators.store).reduce((accumulator, configurator) => {
            if (configurator.isDeleted) {
                return accumulator;
            }

            const option = configurator.internalOption ? configurator.internalOption : configurator.option;
            const groupId = option.groupId;
            const grouped = accumulator[groupId];

            if (grouped) {
                grouped.push(option.id);
                return accumulator;
            }

            accumulator[groupId] = [option.id];

            return accumulator;
        }, {});

        return Object.values(groupedData);
    }

    buildCombinations(data, group = [], value = null, index = 0) {
        const all = [];

        if (value !== null) {
            group.push(value);
        }

        if (index >= data.length) {
            all.push(group);
            return all;
        }

        data[index].forEach((entryValue) => {
            const nested = this.buildCombinations(data, group.slice(), entryValue, index + 1);

            nested.forEach((nestedItem) => {
                all.push(nestedItem);
            });
        });

        return all;
    }

    processQueue(type, queue, offset, limit, resolve) {
        const chunk = queue.slice(offset, offset + limit);
        if (chunk.length <= 0) {
            resolve();
            return;
        }

        this.emit('actualProgressChange', { type: type, progress: offset });

        const payload = [{
            action: type,
            entity: 'product',
            payload: chunk
        }];

        const header = this.EntityStore.getLanguageHeader(this.getLanguageId());
        this.syncService.sync(payload, {}, header).then(() => {
            this.processQueue(type, queue, offset + limit, limit, resolve);
        });
    }

    getLanguageId() {
        return this.languageStore.getCurrentId();
    }
}
