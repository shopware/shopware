/*
 * @sw-package inventory
 */

import EventEmitter from 'events';
import RetryHelper from '../../../core/helper/retry.helper';

const { deepCopyObject } = Shopware.Utils.object;
const { md5 } = Shopware.Utils.format;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class VariantsGenerator extends EventEmitter {
    constructor() {
        super();

        this.product = null;
        this.productIds = [];

        // set dependencies
        this.syncService = Shopware.Service('syncService');
        this.cacheService = Shopware.Service('cacheApiService');
        this.httpClient = this.syncService.httpClient;

        // local data
        this.languageId = null;

        // `product_option` mappings to insert after `saveVariants`, adding the
        // new axis options to preserved variants. Array<{ productId, optionId }>
        this.extendExistingVariantOptions = [];

        // option ids granted via adoption; they never reach the createQueue,
        // so saveConfiguratorSettings reads them from here. Reset per pass,
        // not on flush (settings are saved after the variants).
        this.adoptedOptionIds = new Set();
    }

    /**
     * Saves configurator settings using the sync API directly.
     * This approach avoids issues with stale entity origin state that could cause
     * DELETE requests for settings already cascade-deleted on the server.
     * Only upserts are performed, no deletions.
     *
     * @param {EntityCollection} configuratorSettings - The configurator settings to save
     * @param {Array} createQueue - Queue of new variants to be created (used to determine truly new options)
     * @returns {Promise}
     */
    saveConfiguratorSettings(configuratorSettings, createQueue = []) {
        if (!configuratorSettings || configuratorSettings.length === 0) {
            return Promise.resolve();
        }

        // adopted options never appear in the createQueue but still need their settings saved
        const newOptionIds = new Set(this.adoptedOptionIds);
        createQueue.forEach((variant) => {
            if (variant.options) {
                variant.options.forEach((option) => {
                    newOptionIds.add(option.id);
                });
            }
        });

        const payload = configuratorSettings
            .filter((setting) => {
                if (!setting.isNew()) {
                    return true;
                }

                return newOptionIds.has(setting.optionId);
            })
            .map((setting) => {
                const settingData = deepCopyObject(setting);
                settingData.productId = this.product.id;
                return settingData;
            });

        if (payload.length === 0) {
            return Promise.resolve();
        }

        return this.syncService.sync(
            [
                {
                    entity: 'product_configurator_setting',
                    action: 'upsert',
                    payload,
                },
            ],
            {},
            { 'single-operation': 1 },
        );
    }

    /**
     * Saves the variants to the database via sync api.
     */
    saveVariants(queues) {
        return new Promise((resolveDelete) => {
            // notify view to refresh progress
            this.emit('progress-max', {
                type: 'delete',
                progress: queues.deleteQueue.length,
            });

            // create mapping for api call
            const mapped = queues.deleteQueue.map((id) => {
                return { id };
            });

            // send api calls for delete
            this.processQueue('delete', mapped, 0, 10, resolveDelete);
        })
            .then(() => {
                // notify view to refresh progress
                this.emit('progress-max', {
                    type: 'upsert',
                    progress: queues.createQueue.length,
                });

                return new Promise((resolve) => {
                    // send api calls for create
                    this.processQueue('upsert', queues.createQueue, 0, 10, resolve);
                });
            })
            .then(() => {
                // add the new axis options to preserved variants
                return this.saveExistingVariantOptionExtensions();
            })
            .then(() => {
                this.indexProducts(this.productIds);
            });
    }

    /**
     * Inserts the queued `product_option` mappings for preserved variants.
     *
     * @returns {Promise}
     */
    saveExistingVariantOptionExtensions() {
        if (!this.extendExistingVariantOptions || this.extendExistingVariantOptions.length === 0) {
            return Promise.resolve();
        }

        const payload = this.extendExistingVariantOptions.map((mapping) => ({
            productId: mapping.productId,
            optionId: mapping.optionId,
        }));

        // queue preserved variants for indexing (rebuilds option_ids)
        payload.forEach((mapping) => {
            if (this.productIds.indexOf(mapping.productId) < 0) {
                this.productIds.push(mapping.productId);
            }
        });

        this.extendExistingVariantOptions = [];

        return this.syncService.sync(
            [
                {
                    entity: 'product_option',
                    action: 'upsert',
                    payload,
                },
            ],
            { 'indexing-behavior': 'disable-indexing' },
            { 'single-operation': 1 },
        );
    }

    generateVariants(currencies, product, isAddOnly = false) {
        this.product = product;

        // start every pass clean; the empty-selection path below never
        // reaches filterVariations and its reset
        this.extendExistingVariantOptions = [];
        this.adoptedOptionIds = new Set();

        const configuratorSettings = this.product.configuratorSettings;

        // This check is done to set a default value for completely new generated variants
        // without changing existing configuration
        if (
            !this.product.variantListingConfig ||
            (!this.product.variantListingConfig.displayParent &&
                !this.product.variantListingConfig.configuratorGroupConfig &&
                !this.product.variantListingConfig.mainVariantId)
        ) {
            this.product.variantListingConfig = {};
            this.product.variantListingConfig.displayParent = true;
        }

        return new Promise(() => {
            const grouped = this.groupTheOptions(configuratorSettings);

            // When nothing is selected
            if (grouped.length <= 0) {
                this.loadExisting(this.product.id).then((variantsOnServer) => {
                    const deleteArray = Object.keys(variantsOnServer).map((id) => {
                        return id;
                    });
                    this.emit('queues', {
                        createQueue: [],
                        deleteQueue: isAddOnly ? [] : deleteArray,
                    });
                });
                return;
            }

            // create permutations of variants
            const permutations = this.buildCombinations(grouped);

            this.loadExisting(this.product.id)
                .then((variantsOnServer) => {
                    // filter deletable and creatable variations
                    return this.filterVariations(permutations, variantsOnServer, currencies, isAddOnly);
                })
                .then((queues) => {
                    this.emit('queues', queues);
                });
        });
    }

    filterVariations(newVariations, variationOnServer, currencies, isAddOnly = false) {
        const configuratorSettings = this.product.configuratorSettings;

        // reset any adoption state queued from a previous generation pass
        this.extendExistingVariantOptions = [];
        this.adoptedOptionIds = new Set();

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
            // hash -> sorted option ids, for the subset checks below
            const optionsByHash = {};

            for (const [
                key,
                variant,
            ] of Object.entries(variationOnServer)) {
                const sortedOptions = [...variant.options].sort();
                const hash = md5(JSON.stringify(sortedOptions));
                hashed[hash] = key;
                numberMap[hash] = variant.productNumber;
                numbers[variant.productNumber] = true;
                optionsByHash[hash] = sortedOptions;
            }

            let deleteQueue = [];
            if (!isAddOnly) {
                // Copy the hashed list with the sorted variations on the server.
                deleteQueue = deepCopyObject(hashed);
            }

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
            this.emit('progress-max', {
                type: 'calc',
                progress: newVariationsSorted.length,
            });

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

            /*
             * Preserve existing variants when a new property axis is added:
             * a variant whose options are a strict subset of a new permutation
             * is kept (instead of deleted), the missing options are queued as
             * `product_option` inserts, and the matched permutation is skipped
             * in the create queue. Each variant claims the first unclaimed
             * superset; each permutation can be claimed once.
             */
            const adoptedNewHashes = new Set();
            if (!isAddOnly) {
                // hash all permutations once; re-hashing per variant would be
                // O(existing x permutations)
                const newVariationHashes = newVariationsSorted.map((variation) => md5(JSON.stringify(variation)));

                Object.keys(deleteQueue).forEach((existingHash) => {
                    const existingOptions = optionsByHash[existingHash];
                    if (!existingOptions || existingOptions.length === 0) {
                        return;
                    }

                    const matchIndex = newVariationHashes.findIndex((newHash, idx) => {
                        if (adoptedNewHashes.has(newHash)) {
                            return false;
                        }
                        if (hashed[newHash] !== undefined) {
                            // already exists on the server, not a new permutation
                            return false;
                        }
                        const newOptions = newVariationsSorted[idx];
                        if (newOptions.length <= existingOptions.length) {
                            return false;
                        }
                        // strict subset: every existing option must be in the permutation
                        return existingOptions.every((optionId) => newOptions.includes(optionId));
                    });

                    if (matchIndex === -1) {
                        return;
                    }

                    const matchHash = newVariationHashes[matchIndex];
                    const existingVariantId = deleteQueue[existingHash];
                    const matchedNewOptions = newVariationsSorted[matchIndex];

                    const addedOptionIds = matchedNewOptions.filter((optionId) => !existingOptions.includes(optionId));

                    addedOptionIds.forEach((optionId) => {
                        this.extendExistingVariantOptions.push({
                            productId: existingVariantId,
                            optionId,
                        });
                        this.adoptedOptionIds.add(optionId);
                    });

                    adoptedNewHashes.add(matchHash);

                    // keep the existing variant
                    delete deleteQueue[existingHash];
                });
            }

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

                // adopted by an existing variant, nothing to create
                if (adoptedNewHashes.has(hash)) {
                    return;
                }

                const variations = variation.map((optionId) => {
                    return { id: optionId };
                });

                // new variation price
                let variationPrice = {};

                // Go through each option and add price changes to main price of variation
                variations
                    .map((variationObject) => variationObject.id)
                    .forEach((variationId) => {
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
        if (!Array.isArray(this.product.variantRestrictions)) {
            return createQueue;
        }

        const validRestrictions = this.product.variantRestrictions.filter((restriction) => {
            return (
                restriction &&
                Array.isArray(restriction.values) &&
                restriction.values.length > 0 &&
                restriction.values.every((value) => Array.isArray(value.options) && value.options.length > 0)
            );
        });

        if (validRestrictions.length === 0) {
            return createQueue;
        }

        // Filter to get an array with only the restrictions ids with the single option ids
        const restrictionsOnly = validRestrictions.map((restriction) => {
            return restriction.values.map((value) => {
                return value.options;
            });
        });

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
        return this.httpClient
            .get(`/_action/product/${id}/combinations`, {
                headers: this.syncService.getBasicHeaders(),
            })
            .then((response) => {
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

        const payload = [
            {
                action: type,
                entity: 'product',
                payload: chunk,
            },
        ];

        // Send the payload to the server
        const header = { 'single-operation': 1 };

        RetryHelper.retry(() => {
            return this.syncService.sync(payload, { 'indexing-behavior': 'disable-indexing' }, header);
        }).then((response) => {
            this.productIds.concat(response.data?.product ?? []).concat(response.data?.deleted?.product ?? []);
            this.processQueue(type, queue, offset + limit, limit, resolve);
        });
    }

    indexProducts(productIds) {
        if (productIds.length <= 0) {
            return;
        }

        this.cacheService.indexProducts(productIds);
    }
}
