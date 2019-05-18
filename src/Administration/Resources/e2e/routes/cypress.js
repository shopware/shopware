const express = require('express');
const childProcess = require('child_process');
const app = express();

app.get('/cleanup', (req, res) => {
    return childProcess.exec('./psh.phar e2e:cleanup', (err, stdin) => {
        res.send(stdin);
    });
});

app.listen(8005);
