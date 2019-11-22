const fs = require('fs');
const path = require('path');
const mkdirp = require('mkdirp');

function copyFileUsingFsStreams(from, to) {
    const readStream = fs.createReadStream(from);
    const writeStream = fs.createWriteStream(to);

    return new Promise((resolve, reject) => {
        readStream.on('error', reject);
        writeStream.on('error', reject);
        writeStream.on('finish', resolve);

        readStream.pipe(writeStream);
    });
}

function WebpackCopyAfterBuild(config) {
    this._files = config.files || {};
    this._opts = config.options || {};

    // Transform the files array into a map for a more convenient way to work with it
    this._filesMap = new Map();
    this._files.forEach((file) => {
        this._filesMap.set(file.chunkName, file.to);
    });
}

WebpackCopyAfterBuild.prototype.apply = function webpackCopyAfterBuild(compiler) {
    const files = this._filesMap;
    const opts = this._opts;

    compiler.hooks.done.tap('sw-copy-after-build', (stats) => {
        stats = stats.toJson();
        const chunks = stats.chunks;

        chunks.forEach((chunk) => {
            const chunkName = chunk.names[0];
            const outputPath = compiler.options.output.path;

            // Check if we have to copy any files
            if (!files.has(chunkName)) {
                return true;
            }

            // The path of the file where it should be placed
            const filePath = files.get(chunkName);
            // The base path of the target directory where the file should be placed
            const targetBasePath = path.resolve(filePath, '../');

            // Create the target directory recursively if it not already exists
            if (!fs.existsSync(targetBasePath)) {
                mkdirp.sync(targetBasePath);
            }

            /**
             * Iterate through all files which are grouped by the chunk
             * and copy them to the correct target directory,
             * including JS, CSS and SourceMap files.
             */
            chunk.files.forEach(async (file) => {
                let from = `${outputPath}/${file}`;
                let to = `${targetBasePath}/${file}`;

                if (typeof opts.transformer === 'function') {
                    to = opts.transformer(to);
                }

                const targetPath = path.resolve(to, '../');

                // Create the target sub directory for the specific file type recursively
                if (!fs.existsSync(targetPath)) {
                    mkdirp.sync(targetPath);
                }

                // Support absolute paths
                if (!opts.hasOwnProperty('absolutePath') || !opts.absolutePath) {
                    from = `${outputPath}/${filePath}`;
                }

                if (fs.existsSync(from)) {
                    await copyFileUsingFsStreams(from, to);
                }

                if (fs.existsSync(from)) {
                    fs.unlinkSync(from);
                }
            });

            return true;
        });
    });
};

module.exports = WebpackCopyAfterBuild;
