import { deepCopyObject } from 'src/core/service/utils/object.utils';

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
                    this.syncVariants('delete', deleteArray, 0, 10, resolve);
                });
                return;
            }

            // check for large request over 100 000 variants
            const numberOfVariants = grouped.map((group) => group.length).reduce((curr, length) => curr * length);
            if (!forceGenerating && numberOfVariants >= 10000) {
                this.emit('warning', numberOfVariants);
                reject(new Error('Warning fired'));
                return;
            }

            // create permutations of variants
            const permutations = this.buildCombinations(grouped);

            this.loadExisting(this.product.id).then((variantsOnServer) => {
                const filterVariations = this.filterVariations(permutations, variantsOnServer);

                this.emit('maxProgressChange', { type: 'delete', progress: filterVariations.variationsToDelete.length });

                // first delete variants, then create new variants
                // use promise for creating a recursion to create sequential request
                new Promise((resolveDelete) => {
                    this.syncVariants('delete', filterVariations.variationsToDelete, 0, 100, resolveDelete);
                }).then(() => {
                    this.emit('maxProgressChange', { type: 'upsert', progress: filterVariations.variationsToCreate.length });
                    this.syncVariants('upsert', filterVariations.variationsToCreate, 0, 100, resolve);
                });
            });
        });
    }

    filterVariations(newVariations, variationOnServer) {
        let variationsToDelete = Object.keys(deepCopyObject(variationOnServer));
        const variationsToCreate = [];

        const variationOnServerHashed = Object.entries(variationOnServer).map((variation) => {
            return [variation[0], JSON.stringify(variation[1].sort())];
        });
        const newVariationsSorted = newVariations.map((variation) => variation.sort());

        this.emit('maxProgressChange', { type: 'calc', progress: newVariationsSorted.length });

        newVariationsSorted.forEach((variation, index) => {
            console.log(index);
            this.emit('actualProgressChange', { type: 'calc', progress: index });

            const exist = this.existsHash(variation, variationOnServerHashed);

            if (exist) {
                // Remove from delete list
                variationsToDelete = variationsToDelete.filter(key => key !== exist[0]);
                return false;
            }

            const variations = variation.map((optionId) => {
                return { id: optionId };
            });

            // todo calculate price with surcharges
            // todo consider product.priceRules (store of prices)

            // Add to create list
            variationsToCreate.push({
                parentId: this.product.id,
                variations: variations,
                stock: this.product.stock,
                price: { gross: 200, net: 100, linked: false }
            });

            return true;
        });

        return {
            variationsToDelete: variationsToDelete.map((id) => {
                return { id };
            }),
            variationsToCreate
        };
    }

    existsHash(permutation, existings) {
        const permutationHash = JSON.stringify(permutation);
        const found = existings.find((variation) => {
            return permutationHash === variation[1];
        });
        return found || false;
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

    syncVariants(type, variants, offset, limit, resolve) {
        const chunk = variants.slice(offset, offset + limit);
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
            this.syncVariants(type, variants, offset + limit, limit, resolve);
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
