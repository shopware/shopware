const fs = require('fs');
const path = require('path');
const mkdirp = require('mkdirp');

function WebpackCopyAfterBuild(config) {
    this._files = config.files || {};
    this._opts = config.options || {};

    // Transform the files array into a map for a more convenient way to work with it
    this._filesMap = new Map();
    this._files.forEach((file) => {
        this._filesMap.set(file.chunkName, file.to);
    });
}

WebpackCopyAfterBuild.prototype.apply = function(compiler) {
    const files = this._filesMap;
    const opts = this._opts;

    compiler.plugin('done', (stats) => {
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
            chunk.files.forEach((file) => {
                let from = `${outputPath}/${file}`;
                const to = `${targetBasePath}/${file}`;
                const targetPath = path.resolve(to, '../');

                // Create the target sub directory for the specific file type recursively
                if (!fs.existsSync(targetPath)) {
                    mkdirp.sync(targetPath);
                }

                // Support absolute paths
                if (!opts.hasOwnProperty('absolutePath') || !opts.absolutePath) {
                    from = `${outputPath}/${filePath}`;
                }

                fs.createReadStream(from).pipe(fs.createWriteStream(to));
            });
        });
    });
};

module.exports = WebpackCopyAfterBuild;