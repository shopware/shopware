/**
 * @sw-package framework
 */

const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const vueJest = require('@vue/vue3-jest');
const { SourceMapConsumer, SourceMapGenerator } = require('source-map-js');
const { isDependencyFile, transformShopwareSetupSfc } = require('../../build/vue-setup-transform');

const transformRoot = path.resolve(__dirname, '../../build/vue-setup-transform');

// Jest reuses a cached result as long as the key matches, so the key has to change with the
// transform's own sources instead of running the transform to find out.
const transformVersion = (() => {
    const hash = crypto.createHash('md5').update(fs.readFileSync(__filename));

    fs.readdirSync(transformRoot, { recursive: true })
        .map(String)
        .filter((file) => /\.[jt]s$/.test(file) && !file.includes('.spec'))
        .sort()
        .forEach((file) => hash.update(file).update(fs.readFileSync(path.join(transformRoot, file))));

    return hash.digest('hex');
})();

module.exports = {
    process(source, filename, options) {
        const setup = isDependencyFile(filename) ? null : transformShopwareSetupSfc(source, filename);

        if (!setup) {
            return vueJest.process(source, filename, options);
        }

        const compiled = vueJest.process(setup.code, filename, options);

        // vue-jest takes no input map, so its map points into the transformed SFC. Chaining the
        // transform's map underneath points stack traces and coverage at the authored lines.
        const map = SourceMapGenerator.fromSourceMap(new SourceMapConsumer(JSON.parse(compiled.map)));

        map.applySourceMap(new SourceMapConsumer(setup.map), filename);

        return { code: compiled.code, map: map.toJSON() };
    },

    getCacheKey(source, filename, options) {
        return crypto
            .createHash('md5')
            .update(vueJest.getCacheKey(source, filename, options))
            .update(transformVersion)
            .digest('hex');
    },
};
