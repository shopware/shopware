import { Application, State } from 'src/core/shopware';
import EntityStore from 'src/core/data/EntityStore';
import { deepCopyObject } from 'src/core/service/utils/object.utils';
import EventEmitter from 'events';
import { md5 } from 'src/core/service/utils/format.utils';

export default class VariantsGenerator extends EventEmitter {
    constructor(product) {
        super();

        this.product = product;
        this.configurators = product.configuratorSettings.items;

        // set dependencies
        this.syncService = Application.getContainer('service').syncService;
        this.EntityStore = EntityStore;
        this.State = State;
        this.httpClient = this.syncService.httpClient;

        // local data
        this.languageId = null;
        this.languageStore = this.State.getStore('language');
    }

    createNewVariants(forceGenerating, currencies) {
        return new Promise((resolve) => {
            const grouped = this.groupTheOptions(this.configurators);

            // When nothing is selected, delete everything
            if (grouped.length <= 0) {
                this.loadExisting(this.product.id).then((variantsOnServer) => {
                    const deleteArray = Object.keys(variantsOnServer).map((id) => { return { id }; });
                    this.emit('progress-max', { type: 'delete', progress: deleteArray.length });
                    this.processQueue('delete', deleteArray, 0, 10, resolve);
                });
                return;
            }

            // create permutations of variants
            const permutations = this.buildCombinations(grouped);

            this.loadExisting(this.product.id).then((variantsOnServer) => {
                // filter deletable and creatable variations
                this.filterVariations(permutations, variantsOnServer, currencies)
                    .then((queues) => {
                        if (!forceGenerating) {
                            this.emit('notification', {
                                numberOfDeletions: queues.deleteQueue.length,
                                numberOfCreation: queues.createQueue.length
                            });
                            return;
                        }

                        new Promise((resolveDelete) => {
                            // notify view to refresh progrss
                            this.emit('progress-max', { type: 'delete', progress: queues.deleteQueue.length });

                            // create mapping for api call
                            const mapped = queues.deleteQueue.map((id) => {
                                return { id };
                            });

                            // send api calls for delete
                            this.processQueue('delete', mapped, 0, 10, resolveDelete);
                        }).then(() => {
                        // notify view to refresh progress
                            this.emit('progress-max', { type: 'upsert', progress: queues.createQueue.length });

                            // send api calls for create
                            this.processQueue('upsert', queues.createQueue, 0, 10, resolve);
                        });
                    });
            });
        });
    }

    filterVariations(newVariations, variationOnServer, currencies) {
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
            const numbers = {};
            const numberMap = {};

            // eslint-disable-next-line no-restricted-syntax
            for (const [key, variant] of Object.entries(variationOnServer)) {
                const hash = md5(JSON.stringify(variant.options.sort()));
                hashed[hash] = key;
                numberMap[hash] = variant.productNumber;
                numbers[variant.productNumber] = true;
            }

            // Copy the hashed list with the sorted variations on the server.
            let deleteQueue = deepCopyObject(hashed);

            const newVariationsSorted = newVariations.map((variation) => variation.sort());

            // Get price changes for all option ids
            const priceChanges = Object.values(this.configurators).reduce((result, element) => {
                result.push({
                    id: element.option.id,
                    price: element.price
                });

                return result;
            }, []);

            // notify page that the generation starts now
            this.emit('progress-max', { type: 'calc', progress: newVariationsSorted.length });

            let increment = 1;

            // Check if the new variation exists on the server.
            newVariationsSorted.forEach((variation) => {
                const hash = md5(JSON.stringify(variation));
                const exist = hashed[hash];

                /*
                When the variation exists on the server and in the user selection,
                then remove it from the delete queue. Otherwise create a new variation
                 */
                if (exist !== undefined) {
                    delete deleteQueue[hash];
                }
            });

            Object.keys(deleteQueue).forEach((hash) => {
                delete numbers[numberMap[hash]];
            });

            // Check if the new variation exists on the server.
            newVariationsSorted.forEach((variation) => {
                const hash = md5(JSON.stringify(variation));
                const exist = hashed[hash];

                // handled in above loop
                if (exist !== undefined) {
                    return;
                }

                const variations = variation.map((optionId) => {
                    return { id: optionId };
                });

                // new variation price
                const variationPrice = [];

                // Go through each option and add price changes to main price of variation
                variations.map((variationObject) => variationObject.id).forEach((variationId) => {
                    priceChanges.forEach((option) => {
                        if (option.id === variationId && option.price) {
                            // iterate through each currency
                            option.price.forEach((price) => {
                                // check for price surcharges
                                if (price.gross !== 0 || price.net !== 0) {
                                    // get parent price for currency
                                    const refCurrencyPrice = this.product.price.find((productPrice) => {
                                        return productPrice.currencyId === price.currencyId;
                                    });

                                    let refPrice = refCurrencyPrice;

                                    // use the default price as fallback when no custom price for the currency exists
                                    if (!refCurrencyPrice) {
                                        const defaultCurrency = Object.values(currencies.items).find((currency) => {
                                            return currency.isDefault;
                                        });

                                        const defaultCurrencyPrice = this.product.price.find((productPrice) => {
                                            return productPrice.currencyId === defaultCurrency.id;
                                        });

                                        const actualCurrency = Object.values(currencies.items).find((currency) => {
                                            return currency.id === price.currencyId;
                                        });

                                        // recalculate price for currency with conversion factor
                                        refPrice = {
                                            net: defaultCurrencyPrice.net * actualCurrency.factor,
                                            gross: defaultCurrencyPrice.gross * actualCurrency.factor
                                        };
                                    }

                                    // calculate new price with surcharge
                                    const grossPrice = refPrice.gross + price.gross;
                                    const netPrice = refPrice.net + price.net;

                                    // push new currency price with surcharges to variation price
                                    variationPrice.push({
                                        currencyId: price.currencyId,
                                        gross: grossPrice > 0 ? grossPrice : 0,
                                        linked: price.linked,
                                        net: netPrice > 0 ? netPrice : 0
                                    });
                                }
                            });
                        }
                    });
                });

                // get generated number and increment
                const generated = this.createNumber(this.product.productNumber, increment, numbers);
                increment = generated.increment;

                // create new variant product
                const variantObject = {
                    parentId: this.product.id,
                    options: variations,
                    stock: 0,
                    productNumber: generated.number,
                    taxId: this.product.taxId
                };

                // when variant has custom price then add it to price
                if (variationPrice.length > 0) {
                    variantObject.price = variationPrice;
                }

                // Add to create list
                createQueue.push(variantObject);
            });

            // create an array with only the values
            deleteQueue = Object.values(deleteQueue);

            // filter the create queue with the new restrictions
            const filteredCreateQueue = this.filterRestrictions(createQueue);

            // return the delete and create queue
            resolve({ deleteQueue, createQueue: filteredCreateQueue });
        });
    }

    createNumber(prefix, increment, numbers) {
        let exists = true;
        let number = null;

        // check for the first unused number
        while (exists) {
            number = `${prefix}.${increment}`;
            exists = numbers.hasOwnProperty(number);
            increment += 1;
        }

        return { number, increment };
    }

    filterRestrictions(createQueue) {
        const variantRestriction = this.product.variantRestrictions || [];

        // Filter to get an array with only the restrictions ids with the single option ids
        const restrictionsOnly = variantRestriction.map((restriction) => {
            return restriction.values.map((value) => {
                return value.options;
            });
        });

        // Return the normal create queue when the user does not create restrictions
        if (restrictionsOnly.length <= 0) {
            return createQueue;
        }

        /**
         * Go through the whole createQueue and check for each variation,
         * if the option combination matches one of the restrictions
         */
        const filteredCreateQueue = createQueue.filter((newVariation) => {
            const variations = newVariation.options.map((variation) => variation.id);

            return restrictionsOnly.reduce((result, restriction) => {
                const hasRestriction = restriction.reduce((exists, restrictionArray) => {
                    const restrictionExist = restrictionArray.find((optionId) => {
                        return variations.indexOf(optionId) >= 0;
                    });

                    return restrictionExist ? exists : false;
                }, true);

                return hasRestriction ? false : result;
            }, true);
        });

        return filteredCreateQueue;
    }

    loadExisting(id) {
        // Return all existing variations from the server
        return this.httpClient.get(
            `/_action/product/${id}/combinations`,
            { headers: this.syncService.getBasicHeaders() }
        ).then((response) => {
            return response.data;
        });
    }

    groupTheOptions(configurators) {
        // get all selected group id with the selected options
        //
        // {
        //     groupId: [optionId, optionId, ...],
        //     groupId: [optionId, optionId, ...],
        //     ...
        // }

        const groupedData = Object.values(configurators).reduce((accumulator, configurator) => {
            const groupId = configurator.option.groupId;
            const grouped = accumulator[groupId];

            if (grouped) {
                grouped.push(configurator.option.id);
                return accumulator;
            }

            accumulator[groupId] = [configurator.option.id];

            return accumulator;
        }, {});

        // Return only the grouped options
        return Object.values(groupedData);
    }

    buildCombinations(data, group = [], value = null, index = 0) {
        // Recursion which build the permutation of all options (sorted in groups)
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
        // Create a chunk
        const chunk = queue.slice(offset, offset + limit);
        if (chunk.length <= 0) {
            resolve();
            return;
        }

        // Emit the progress to the view
        this.emit('progress-actual', { type: type, progress: offset });

        const payload = [{
            action: type,
            entity: 'product',
            payload: chunk
        }];

        // Send the payload to the server
        const header = this.EntityStore.getLanguageHeader(this.getLanguageId());
        this.syncService.sync(payload, {}, header).then(() => {
            this.processQueue(type, queue, offset + limit, limit, resolve);
        });
    }

    getLanguageId() {
        return this.languageStore.getCurrentId();
    }
}
