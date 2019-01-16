global.logger = {
    log: createLog,
    success: createSuccessEntry,
    error: createErrorEntry,
    title: createTitle,
    lineBreak: createNewLine
};

function createSuccessEntry(messages) {
    createLog('success', messages);
}

function createErrorEntry(messages) {
    createLog('error', messages);
}

function createTitle(messages) {
    createLog('title', messages);
}
function createNewLine() {
    createLog();
}

function createLog(type, ...message) {
    let symbol = (type === 'success') ? '• ✓' : '';
    symbol = (type === 'error') ? '• ✖' : symbol;

    if (type === 'title') {
        symbol = '###';
    }
    message.unshift(`${symbol}`);
    console.log.apply(this, message);
}