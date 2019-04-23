<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shopware administration</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/static/img/favicon/favicon-32x32.png" id="dynamic-favicon">
</head>
<body>
    <div id="app"></div>
    <script type="text/javascript">
        Shopware.Application.start({ features: <%= featureFlags %> });
    </script>
</body>
</html>
