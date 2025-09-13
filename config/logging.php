<?php

return [
    'driver' => 'file',
    'file' => [
        'path' => BASE_PATH . '/storage/logs/',
        'levels' => [
            'emergency' => 'emergency.log',
            'alert'     => 'alert.log',
            'critical'  => 'critical.log',
            'error'     => 'error.log',
            'warning'   => 'warning.log',
            'notice'    => 'notice.log',
            'info'      => 'info.log',
            'debug'     => 'debug.log',
        ]
    ]
];
