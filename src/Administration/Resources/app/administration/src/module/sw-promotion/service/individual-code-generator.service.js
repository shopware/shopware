import EventEmitter from 'events';
import CodeGenerator from './code-generator.service';
import Criteria from '../../../core/data/criteria.data';

/**
 * @deprecated tag:v6.5.0 - will be removed, use `sw-promotion-v2` instead
 */
export default class IndividualCodeGenerator extends EventEmitter {
    /**
     * Code saver service, which generates codes and saves them using the provided repository
     *
     * @constructor
     * @param {String} promotionId - The Id of the promotion
     * @param {Repository} repository - PromotionIndividualCode Repository
     * @param {Object} context - The current context of the Admin
     */
    constructor(promotionId, repository, context) {
        super();

        this.promotionId = promotionId;
        this.repository = repository;
        this.context = context;

        // chunk size 50 ~ 7kb
        this.chunkSize = 100;
        this.maxRetryCount = 5;

        this.syncService = Shopware.Service('syncService');
        this.httpClient = this.syncService.httpClient;
    }

    /**
     * Generates a list of codes using the
     * provided code pattern.
     *
     * @param {String} pattern - The string with placeholders, like 'my-code-%d%s'
     * @param {Number} desiredCount - Maximum number of codes to generate
     */
    async generateCodes(pattern, desiredCount) {
        const result = {
            count: 0,
        };

        this.emit('generate-begin', {
            maxCount: desiredCount,
        });

        // remove all existing codes
        // before generating new ones
        await this.removeExistingCodes();

        this.emit('generate-cleared', {});

        // start our main process in a separate promise.
        // this function will automatically chain itself by using "retries"
        // if the number of generated codes is not met after the first run.
        const promise = new Promise((onMainProcessCompleted) => {
            this.startMainProcess(pattern, desiredCount, 0, 0, onMainProcessCompleted);
        });

        // start, resolve and grab our count of the generated codes.
        await Promise.resolve(promise).then((totalGeneratedCount) => {
            result.count = totalGeneratedCount;
        });

        this.emit('generate-end', result);

        return result;
    }

    /**
     * This function removes all individual codes of
     * the promotion with the current id
     */
    async removeExistingCodes() {
        // Return all existing variations from the server
        return this.httpClient.delete(
            `/_action/promotion/${this.promotionId}/codes/individual`,
            {
                headers: this.syncService.getBasicHeaders(),
            },
        ).then((response) => {
            return response.data;
        });
    }

    /**
     * This function loads all individual codes of
     * the promotion with the current id.
     * The result will be a flat array of strings.
     */
    async loadExistingCodes() {
        // Return all existing variations from the server
        return this.httpClient.get(
            `/_action/promotion/${this.promotionId}/codes/individual`,
            {
                headers: this.syncService.getBasicHeaders(),
            },
        ).then((response) => {
            return response.data;
        });
    }

    /**
     * Starts our chained main process which might only run 1x in most circumstances.
     * It will generate the required number of codes and try to save them
     * in our database using a "sync" command with multiple actions.
     * If the number of existing codes in our database is enough afterwards, it will
     * resolve our promise, or chain a new main process as long as the
     * maximum retries are not met.
     *
     * @param {String} pattern - The string with placeholders, like 'my-code-%d%s'
     * @param {Number} desiredCount - The number of codes in the database before we started our generation
     * @param {Number} runCount - The current run count of how many times our main process have been restarted.
     * @param {Number} countGenerated - The total number of already generated codes.
     * @param {Function} onCompleted - The completion function that can be called if we have finished our generation
     */
    async startMainProcess(pattern, desiredCount, runCount, countGenerated, onCompleted) {
        let maxGenerationCount = desiredCount;
        let existingCodes = [];

        // we start by fetching our current codes from the
        // database to have
        //      a) the total starting count
        //      b) the existing codes to avoid duplicates when generating it.
        await this.loadExistingCodes().then((codesOnServer) => {
            existingCodes = codesOnServer;
        });

        // calculate the maximum number of permutations
        // that are even possible for this pattern
        const maxPermutationCount = CodeGenerator.getPermutationCount(pattern);

        // calculate how many possible codes would be left
        // in the database
        const possibleCount = maxPermutationCount - existingCodes.length;

        // if our possible codes are less than our desired count
        // then reduce that one
        if (possibleCount < maxGenerationCount) {
            maxGenerationCount = possibleCount;
        }

        if (maxGenerationCount <= 0) {
            onCompleted(0);
            return;
        }

        // generate a new number of codes if necessary
        const allNewCodes = createCodes(pattern, desiredCount, existingCodes, this.promotionId);

        if (allNewCodes.length <= 0) {
            onCompleted(0);
            return;
        }

        this.startSync(allNewCodes, countGenerated)
            .then(() => {
                // increase our run count
                runCount += 1;
                // check how many codes have really been inserted in our database
                this.getTotalDatabaseCount().then((databaseCount) => {
                    // calculate our DIFF value how many codes have been generated
                    countGenerated += databaseCount - existingCodes.length;

                    if (countGenerated >= desiredCount) {
                        // we're done with generation :)
                        onCompleted(countGenerated);
                        return;
                    }

                    if (runCount >= this.maxRetryCount) {
                        // break if we have retried too often
                        onCompleted(countGenerated);
                        return;
                    }

                    // we're not yet done, and give it another shot with a new run
                    const leftToGenerate = desiredCount - countGenerated;
                    this.startMainProcess(pattern, leftToGenerate, runCount, countGenerated, onCompleted);
                });
            });
    }

    /**
     * Gets the total number of existing database codes.
     * It will reduce the load by setting a limit for only 1.
     * We only need the additional "total" meta data from the result.
     */
    async getTotalDatabaseCount() {
        const criteria = new Criteria();
        criteria.setLimit(1);
        criteria.addFilter(Criteria.equals('promotionId', this.promotionId));
        criteria.setTotalCountMode(1);

        let count = 0;

        await this.repository.search(criteria, this.context).then((response) => {
            count = response.total;
        });

        return count;
    }

    /**
     * Starts the actual sync for an existing list of Individual Codes.
     *
     * @param {Array} allNewCodes - The Entity list of new codes that should be saved.
     * @param {Number} alreadyGeneratedCount - Count of already generated codes within our process.
     */
    async startSync(allNewCodes, alreadyGeneratedCount) {
        return new Promise((onSyncChunksCompleted) => {
            this.recProcessQueue(allNewCodes, 0, alreadyGeneratedCount, onSyncChunksCompleted);
        });
    }

    /**
     * Processes the queue of individual codes and tries to save it in the database.
     * It will also fetch the database count after a REST call
     * to immediately fire a progress event for the UI.
     *
     * @param {Array} queue - The Entity list of new codes that should be saved.
     * @param {Number} offset - The starting index from our queue that should be used.
     * @param {Number} recGeneratedCount - Number of generated count in this process
     * @param {Function} onCompleted - The completion function that can be called if we have finished sync.
     */
    recProcessQueue(queue, offset, recGeneratedCount, onCompleted) {
        // fetch our new chunk from our current offset
        const chunk = queue.slice(offset, offset + this.chunkSize);

        // check if we have any data left.
        // if not, call our completion function.
        if (chunk.length <= 0) {
            onCompleted();
            return;
        }

        const actions = [];

        // the sync data should actually have 1 action
        // with all items in the payload.
        // unfortunately this would mean an "all or nothing" upsert.
        // if we just have a small number of not existing codes in a range
        // then this would never work.
        // thus we create a separate action for each item which would lead
        // to a list of OR upserts for each item.
        chunk.forEach((item) => {
            actions.push({
                action: 'upsert',
                entity: 'promotion_individual_code',
                payload: [item],
            });
        });

        // use the sync service to save
        // our new codes in the database.
        this.syncService.sync(actions, {}, { 'fail-on-error': false })
            .then((response) => {
                // calculate the diff count which is really
                // the generated count that is successfully saved in the database.
                // so we iterate through all data and their results and
                // just add the number of generated individual codes.
                if (response.data.length > 0) {
                    response.data.forEach((data) => {
                        data.result.forEach((result) => {
                            if ('promotion_individual_code' in result.entities) {
                                recGeneratedCount += result.entities.promotion_individual_code.length;
                            }
                        });
                    });
                }

                // fire our progress event
                this.emit('generate-progress', { progress: recGeneratedCount });

                // handle additional queue entries by calling our function one more time
                this.recProcessQueue(queue, offset + this.chunkSize, recGeneratedCount, onCompleted);
            });
    }
}

/**
 * Creates a new random list of individual code entities
 * and returns it as an array.
 * Only codes that do not already exist, are being generated and returned.
 *
 * @param {String} pattern - The string with placeholders, like 'my-code-%d%s'
 * @param {Number} count - Maximum number of codes to generate
 * @param {Array} existingCodes - List of existing codes that must not be generated again
 * @param {Number} promotionId - The promotion Id of the new code
 */
export function createCodes(pattern, count, existingCodes, promotionId) {
    let i = 0;
    const allNewCodes = [];
    const plainNewCodesList = [];
    const codeLimit = 10000;

    do {
        // generate a new random code
        const randomCode = CodeGenerator.generateCode(pattern);

        if (!existingCodes.includes(randomCode) && !plainNewCodesList.includes(randomCode)) {
            const codeObject = {
                promotionId,
                code: randomCode,
            };

            allNewCodes.push(codeObject);

            plainNewCodesList.push(randomCode);
        }

        i += 1;

        if (i >= codeLimit) {
            break;
        }
    } while (plainNewCodesList.length < count);

    return allNewCodes;
}
