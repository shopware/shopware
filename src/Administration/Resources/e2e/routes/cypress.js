const express = require('express');
const childProcess = require('child_process');

const app = express();

app.get('/cleanup', (req, res) => {
    return childProcess.exec('./psh.phar e2e:cleanup', (err, stdin) => {
        if (err) {
            res.status(500).send(err.message);
            return;
        }

        if (!stdin.includes('All commands successfully executed!')) {
            res.status(500).send(stdin);
            return;
        }

        res.send(stdin);
    });
});

app.listen(8005);
