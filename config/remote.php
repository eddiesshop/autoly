<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Remote Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default connection that will be used for SSH
    | operations. This name should correspond to a connection name below
    | in the server list. Each connection will be manually accessible.
    |
    */

    'default' => 'pre-production',

    /*
    |--------------------------------------------------------------------------
    | Remote Server Connections
    |--------------------------------------------------------------------------
    |
    | These are the servers that will be accessible via the SSH task runner
    | facilities of Laravel. This feature radically simplifies executing
    | tasks on your servers, such as deploying out these applications.
    |
    */

    'connections' => [
        'pre-production' => [
            'host'      => getenv('PREPROD_HOST'),
            'username'  => getenv('PREPROD_USER'),
            'password'  => '',
            'key'       => getenv('PREPROD_KEY'),
            'keytext'   => '',
            'keyphrase' => '',
            'agent'     => '',
            'timeout'   => 10,
        ],
        'stage_1' => [
            'host'      => getenv('STAGE1_HOST'),
            'username'  => getenv('STAGE_USER'),
            'password'  => '',
            'key'       => getenv('STAGE_KEY'),
            'keytext'   => '',
            'keyphrase' => '',
            'agent'     => '',
            'timeout'   => 10,
        ],
        'stage_2' => [
            'host'      => getenv('STAGE2_HOST'),
            'username'  => getenv('STAGE_USER'),
            'password'  => '',
            'key'       => getenv('STAGE_KEY'),
            'keytext'   => '',
            'keyphrase' => '',
            'agent'     => '',
            'timeout'   => 10,
        ],
        'pre-release' => [
            'host'      => getenv('PRERELEASE_HOST'),
            'username'  => getenv('PREPROD_USER'),
            'password'  => '',
            'key'       => getenv('PREPROD_KEY'),
            'keytext'   => '',
            'keyphrase' => '',
            'agent'     => '',
            'timeout'   => 10,
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Remote Server Groups
    |--------------------------------------------------------------------------
    |
    | Here you may list connections under a single group name, which allows
    | you to easily access all of the servers at once using a short name
    | that is extremely easy to remember, such as "web" or "database".
    |
    */

    'groups' => [
        'preprod' => [
            'pre-production'
        ],
        'staging' => [
            'stage_1',
            'stage_2',
        ]
    ],

];
