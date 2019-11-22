<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shopware administration</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/static/img/favicon/favicon-32x32.png" id="dynamic-favicon">

    <link href="/static/css/vendors-node.css" rel="stylesheet">
    <link href="/static/css/app.css" rel="stylesheet">

    <script type="text/javascript" src="/static/js/runtime.js"></script>
    <script type="text/javascript" src="/static/js/vendors-node.js"></script>
    <script type="text/javascript" src="/static/js/commons.js"></script>
    <script type="text/javascript" src="/static/js/app.js"></script>

</head>
<body>
    <div id="app"></div>
    <script type="text/javascript">
        Shopware.Application.start({
            appContext: {
                features: <%= featureFlags %>,
                firstRunWizard: false,
                systemCurrencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca'
            }
        });
    </script>
</body>
</html>
