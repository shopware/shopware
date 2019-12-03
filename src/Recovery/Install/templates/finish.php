<input type="hidden" id="adminUrl" value="<?= $url . '/admin'; ?>" />
<input type="hidden" id="loginTokenData" value="<?= htmlspecialchars(json_encode($loginTokenData)); ?>" />

<script src="<?= $baseUrl; ?>../assets/common/javascript/jquery-3.4.1.min.js"></script>
<script>
    let loginTokenData = JSON.parse(document.getElementById('loginTokenData').value);
    if (loginTokenData) {
        loginTokenData.expiry = Math.round(+new Date() / 1000) + loginTokenData.expires_in;
        localStorage.setItem('bearerAuth', JSON.stringify(loginTokenData));
        document.location = document.getElementById('adminUrl').value;
    }
</script>
