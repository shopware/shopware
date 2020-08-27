<?php declare(strict_types=1);

return [
    'Data store' => <<<'EOD'
These modules are related to database tables and are manageable through the API. Simple CRUD actions will be available.
EOD
    ,
    'Maintenance' => <<<'EOD'
Provide commands executable through CLI to trigger maintenance tasks.
EOD
    ,
    'Custom actions' => <<<'EOD'
These modules contain more than simple CRUD actions. They provide special actions and services that ease management and additionally check consistency.
EOD
    ,
    'SalesChannel-API' => <<<'EOD'
These modules provide logic through a sales channel for the storefront.
EOD
    ,
    'Custom Extendable' => <<<'EOD'
These modules contain interfaces, process container tags or provide custom events as extension points.
EOD
    ,
    'Business Event Dispatcher' => <<<'EOD'
Provide special events to handle business cases.
EOD
    ,
    'Extension' => <<<'EOD'
These modules contain extensions of - usually Framework - interfaces and classes to provide more specific functions for Shopware 6.
EOD
    ,
    'Custom Rules' => <<<'EOD'
Cross-system process to validate workflow decisions.
EOD
    ,
];
