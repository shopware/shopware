const docblockParser = require('docblock-parser');

module.exports = (ast) => {
    if (!ast.comments || !ast.comments[0]) {
        return {};
    }

    let comment = ast.comments[0].value;
    comment = `/**\n${comment}\n*/`;

    const result = docblockParser({
        tags: {
            public: docblockParser.booleanTag,
            private: docblockParser.booleanTag,
            description: docblockParser.multilineTilTag,
            'example-type': docblockParser.singleParameterTag,
            'component-example': docblockParser.multilineTilTag,
            status: docblockParser.singleParameterTag
        }
    }).parse(comment);

    return {
        public: result.tags.public,
        private: result.tags.private,
        example: result.tags['component-example'] || '',
        exampleType: result.tags['example-type'] || 'none',
        status: result.tags.status || 'n/a',
        description: result.tags.description || ''
    };
};