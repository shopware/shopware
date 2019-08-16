const babelOptions = {
    plugins: ['require-context-hook']
};

module.exports = require('babel-jest').createTransformer(babelOptions);
