const fs = require('fs');

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
            const mapping = files.get(chunkName);
            const path = mapping.replace(chunkName + '.js', '');

            // Check if the output directory is there, if falsy create the directory
            if (!fs.existsSync(path)) {
                fs.mkdirSync(path);
            }

            let from = `${outputPath}/${chunk.files[0]}`;
            const to = mapping;

            // Support absolute paths
            if (!opts.hasOwnProperty('absolutePath') || !opts.absolutePath) {
                from = `${outputPath}/${mapping}`;
            }

            fs.createReadStream(from).pipe(fs.createWriteStream(to));

            // Copy source map if the option is set
            if (opts.hasOwnProperty('sourceMap') && opts.sourceMap === true) {
                fs.createReadStream(`${from}.map`).pipe(fs.createWriteStream(`${to}.map`));
            }
        });
    });
}
module.exports = WebpackCopyAfterBuild;