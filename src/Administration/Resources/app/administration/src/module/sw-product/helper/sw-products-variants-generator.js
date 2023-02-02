/*
 * @package inventory
 */

import EventEmitter from 'events';

const { deepCopyObject } = Shopware.Utils.object;
const { md5 } = Shopware.Utils.format;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class VariantsGenerator extends EventEmitter {
    constructor() {
        super();

        this.product = null;

        // set dependencies
        this.syncService = Shopware.Service('syncService');
        this.httpClient = this.syncService.httpClient;

        // local data
        this.languageId = null;
    }

    /*
     * Saves the variants to the database via sync api.
     */
    saveVariants(queues) {
        return new Promise((resolveDelete) => {
            // notify view to refresh progress
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

            return new Promise((resolve) => {
                // send api calls for create
                this.processQueue('upsert', queues.createQueue, 0, 10, resolve);
            });
        });
    }

    generateVariants(
        currencies,
        product,
    ) {
        this.product = product;
        const configuratorSettings = this.product.configuratorSettings;

        // This check is done to set a default value for completely new generated variants
        // without changing existing configuration
        if (!this.product.variantListingConfig
            || (!this.product.variantListingConfig.displayParent
                && !this.product.variantListingConfig.configuratorGroupConfig
                && !this.product.variantListingConfig.mainVariantId)) {
            this.product.variantListingConfig = {};
            this.product.variantListingConfig.displayParent = true;
        }

        return new Promise(() => {
            const grouped = this.groupTheOptions(configuratorSettings);

            // When nothing is selected
            if (grouped.length <= 0) {
                this.loadExisting(this.product.id).then((variantsOnServer) => {
                    const deleteArray = Object.keys(variantsOnServer).map((id) => { return id; });
                    this.emit('queues', { createQueue: [], deleteQueue: deleteArray });
                });
                return;
            }

            // create permutations of variants
            const permutations = this.buildCombinations(grouped);

            this.loadExisting(this.product.id).then((variantsOnServer) => {
                // filter deletable and creatable variations
                return this.filterVariations(permutations, variantsOnServer, currencies);
            }).then((queues) => {
                this.emit('queues', queues);
            });
        });
    }

    filterVariations(
        newVariations,
        variationOnServer,
        currencies,
    ) {
        const configuratorSettings = this.product.configuratorSettings;

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

            // eslint-disable-next-line
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
            const priceChanges = configuratorSettings.reduce((result, element) => {
                result.push({
                    id: element.option.id,
                    price: element.price,
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
                let variationPrice = {};

                // Go through each option and add price changes to main price of variation
                variations.map((variationObject) => variationObject.id).forEach((variationId) => {
                    priceChanges.forEach((option) => {
                        if (!option.price) {
                            return;
                        }

                        if (option.id !== variationId) {
                            return;
                        }

                        // iterate through each currency
                        option.price.forEach((price) => {
                            const currencyId = price.currencyId;

                            let refCurrencyPrice;

                            if (variationPrice[currencyId]) {
                                refCurrencyPrice = variationPrice[currencyId];
                            } else {
                                // get parent price for currency
                                refCurrencyPrice = this.product.price.find((productPrice) => {
                                    return productPrice.currencyId === price.currencyId;
                                });
                            }

                            let refPrice = refCurrencyPrice;

                            // use the default price as fallback when no custom price for the currency exists
                            if (!refCurrencyPrice) {
                                const defaultCurrency = currencies.find((currency) => {
                                    return currency.isSystemDefault;
                                });

                                const defaultCurrencyPrice = this.product.price.find((productPrice) => {
                                    return productPrice.currencyId === defaultCurrency.id;
                                });

                                const actualCurrency = currencies.find((currency) => {
                                    return currency.id === price.currencyId;
                                });

                                // recalculate price for currency with conversion factor
                                refPrice = {
                                    net: defaultCurrencyPrice.net * actualCurrency.factor,
                                    gross: defaultCurrencyPrice.gross * actualCurrency.factor,
                                };
                            }

                            // calculate new price with surcharge
                            const grossPrice = refPrice.gross + price.gross;
                            const netPrice = refPrice.net + price.net;

                            // push new currency price with surcharges to variation price
                            variationPrice[currencyId] = {
                                currencyId: price.currencyId,
                                gross: grossPrice,
                                linked: price.linked,
                                net: netPrice,
                            };
                        });
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
                };

                variationPrice = Object.values(variationPrice);

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
        return createQueue.filter((newVariation) => {
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
    }

    loadExisting(id) {
        // Return all existing variations from the server
        return this.httpClient.get(
            `/_action/product/${id}/combinations`,
            { headers: this.syncService.getBasicHeaders() },
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

        const groupedData = configurators.reduce((accumulator, configurator) => {
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
            payload: chunk,
        }];

        // Send the payload to the server
        const header = { 'single-operation': 1 };

        this.syncService.sync(payload, {}, header).then(() => {
            this.processQueue(type, queue, offset + limit, limit, resolve);
        });
    }
}
