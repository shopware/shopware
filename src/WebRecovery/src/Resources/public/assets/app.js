const decoder = new TextDecoder();

async function tailLog(response, element) {
    const reader = response.body.getReader();

    while (true) {
        const {value, done} = await reader.read();
        if (done) break;

        const text = decoder.decode(value);

        try {
            const result = JSON.parse(text.split("\n").pop());

            if (!result.success) {
                throw new Error('update failed');
            }

            return result;
        } catch (e) {
            element.innerHTML += text;
            element.scrollTop = element.scrollHeight;
        }
    }

    throw new Error('Unexpected end of stream');
}

const installButton = document.getElementById('install-start');
const updateButton = document.getElementById('update-start');

if (installButton) {
    const installLogCard = document.getElementById('install-log-card');
    const installLogOutput = document.getElementById('install-log-output');
    const installLogError = document.getElementById('install-log-error');
    installButton.onclick = async function () {
        installLogCard.style.removeProperty('display');

        installButton.disabled = true;

        const installResponse = await fetch(`${baseUrl}/install/_run`, {method: 'POST'});

        const result = await tailLog(installResponse, installLogOutput);
        if (result.newLocation) {
            window.location = result.newLocation;
        }

        if (!result.success) {
            installLogError.style.removeProperty('display');
        }
    }
}

if (updateButton) {
    const updateLogCard = document.getElementById('update-log-card');
    const updateLogOutput = document.getElementById('update-log-output');

    updateButton.onclick = async function () {
        updateButton.disabled = true;
        updateLogCard.style.removeProperty('display');

        const prepareUpdate = await fetch(`${baseUrl}/update/_prepare`, {method: 'POST'})
        if (prepareUpdate.status !== 200) {
            updateLogOutput.innerHTML += 'Failed to prepare update' + "\n"
            return;
        } else {
            await tailLog(prepareUpdate, updateLogOutput);
        }

        if (!isFlexProject) {
            updateLogOutput.innerHTML += 'Updating to Flex Project' + "\n"

            const migrate = await fetch(`${baseUrl}/update/_migrate-template`, {method: 'POST'})

            if (migrate.status !== 204) {
                updateLogOutput.innerHTML += 'Failed to update to Flex Project' + "\n"
                updateLogCard.innerHTML += await migrate.text();
                return;
            } else {
                updateLogOutput.innerHTML += 'Updated to Flex Project' + "\n"
            }
        }

        const updateRun = await fetch(`${baseUrl}/update/_run`, {method: 'POST'});

        await tailLog(updateRun, updateLogOutput);

        const resetConfig = await fetch(`${baseUrl}/update/_reset_config`, {method: 'POST'});

        if (resetConfig.status !== 200) {
            updateLogOutput.innerHTML += 'Failed to update config files' + "\n"
            updateLogOutput.innerHTML += await resetConfig.text();
        } else {
            await tailLog(resetConfig, updateLogOutput);
        }

        const finishUpdate = await fetch(`${baseUrl}/update/_finish`, {method: 'POST'})
        if (finishUpdate.status !== 200) {
            updateLogOutput.innerHTML += 'Failed to prepare update' + "\n"
            updateLogOutput.innerHTML += await finishUpdate.text();
        } else {
            await tailLog(finishUpdate, updateLogOutput);
        }

        window.location.href = `${baseUrl}/finish`;
    }
}

