import { types } from 'src/core/service/util.service';
import EventEmitter from 'events';

export default class VariantsGenerator extends EventEmitter {
    constructor(dependencies) {
        super();
        // set dependencies
        this.product = dependencies.product;
        this.syncService = dependencies.syncService;
        this.EntityStore = dependencies.EntityStore;
        this.State = dependencies.State;
        this.$tc = dependencies.$tc;
        this.httpClient = this.syncService.httpClient;

        // local data
        this.configuratorStore = this.product.getAssociation('configurators');
        this.languageId = null;
        this.productStore = this.State.getStore('product');
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
                    this.sendDeletion(deleteArray, 0, 10, resolve);
                });
                return;
            }

            // check for large request over 100 000 variants
            const numberOfVariants = grouped.map((group) => group.length).reduce((curr, length) => curr * length);
            if (!forceGenerating && numberOfVariants >= 100000) {
                this.emit('warning', numberOfVariants);
                reject(new Error('Warning fired'));
                return;
            }

            // create permutations of variants
            const permutations = this.buildCombinations(grouped);

            this.loadExisting(this.product.id).then((variantsOnServer) => {
                const variantsToCreate = this.filterExistingProducts(permutations, variantsOnServer);
                const variantsToDelete = this.filterDeleteProducts(permutations, variantsOnServer).map((id) => {
                    return { id };
                });

                this.emit('maxProgressChange', { type: 'delete', progress: variantsToDelete.length });

                // first delete variants, then create new variants
                // use promise for creating a recursion to create sequential request
                new Promise((resolveDelete) => {
                    this.sendDeletion(variantsToDelete, 0, 10, resolveDelete);
                }).then(() => {
                    this.emit('maxProgressChange', { type: 'create', progress: variantsToCreate.length });
                    this.sendVariants(variantsToCreate, 0, 10, resolve);
                });
            });
        });
    }

    filterExistingProducts(newVariations, variantsOnServer) {
        const neededVariants = [];

        // todo filter restrictions
        newVariations.forEach((variation) => {
            if (this.exists(variation, variantsOnServer)) {
                return false;
            }

            const variations = variation.map((optionId) => {
                return { id: optionId };
            });

            // todo calculate price with surcharges
            // todo consider product.priceRules (store of prices)

            neededVariants.push({
                parentId: this.product.id,
                variations: variations,
                stock: this.product.stock,
                price: { gross: 200, net: 100, linked: false }
            });

            return true;
        });

        return neededVariants;
    }

    filterDeleteProducts(newVariations, variantsOnServer) {
        const deletableVariations = [];
        const variantCombinationOnServer = Object.entries(variantsOnServer);

        // Loop through the server variation and check if they should exists
        // otherwise add it to deletableVariations[]
        variantCombinationOnServer.forEach((variation) => {
            if (this.exists(variation[1], newVariations)) {
                return false;
            }

            deletableVariations.push(variation[0]);
            return true;
        });
        return deletableVariations;
    }


    loadExisting(id) {
        // todo create a service for this request
        return this.httpClient.get(
            `/_action/product/${id}/combinations`,
            { headers: this.syncService.getBasicHeaders() }
        ).then((response) => {
            return response.data;
        });
    }

    exists(permutation, existings) {
        let returnValue = false;
        Object.keys(existings).forEach((key) => {
            const options = existings[key];

            // option count do not match? skip this existing
            if (options.length === permutation.length) {
                // check same options
                const diff = types.difference(options, permutation);

                if (diff.length <= 0) {
                    returnValue = true;
                }
            }
        });

        return returnValue;
    }

    groupTheOptions(configurators) {
        const groupedData = {};

        configurators.forEach((configurator) => {
            if (configurator.isDeleted) {
                return;
            }

            let option = configurator.option;
            if (configurator.internalOption) {
                option = configurator.internalOption;
            }

            const groupId = option.groupId;
            const grouped = groupedData[groupId];

            if (grouped) {
                grouped.push(option.id);
                return;
            }

            groupedData[groupId] = [option.id];
        });

        return Object.values(groupedData);
    }

    buildCombinations(data, group = [], val = null, i = 0) {
        const all = [];

        if (val !== null) {
            group.push(val);
        }

        if (i >= data.length) {
            all.push(group);
            return all;
        }

        data[i].forEach((v) => {
            const x = i + 1;
            const nested = this.buildCombinations(data, group.slice(), v, x);

            nested.forEach((nestedItem) => {
                all.push(nestedItem);
            });
        });

        return all;
    }

    sendVariants(variants, offset, limit, resolve) {
        const chunk = variants.slice(offset, offset + limit);

        if (chunk.length <= 0) {
            resolve();
            return;
        }

        this.emit('actualProgressChange', { type: 'create', progress: offset });

        const payload = [{
            action: 'upsert',
            entity: 'product',
            payload: chunk
        }];
        const header = this.EntityStore.getLanguageHeader(this.getLanguageId());

        this.syncService.sync(payload, {}, header).then(() => {
            this.sendVariants(variants, offset + limit, limit, resolve);
        });
    }

    sendDeletion(variants, offset, limit, resolve) {
        const chunk = variants.slice(offset, offset + limit);

        if (chunk.length <= 0) {
            resolve();
            return;
        }

        this.emit('actualProgressChange', { type: 'delete', progress: offset });

        const payload = [{
            action: 'delete',
            entity: 'product',
            payload: chunk
        }];

        const header = this.EntityStore.getLanguageHeader(this.getLanguageId());

        this.syncService.sync(payload, {}, header).then(() => {
            this.sendDeletion(variants, offset + limit, limit, resolve);
        });
    }

    getLanguageId() {
        if (this.languageId === null) {
            const store = this.State.getStore('language');

            this.languageId = store.getCurrentId();
        }
        return this.languageId;
    }
}
