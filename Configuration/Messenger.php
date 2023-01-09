<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_tactician" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

use Ssch\T3Messenger\Command\MyCommand;

return [
    'failure_transport' => 'failed',
    'transports' => [
        'async' => [
            'dsn' => 'typo3-db://',
            'options' => [
                'queue_name' => 'async',
            ],
            'retry_strategy' => [
                'service' => null,
                'max_retries' => 1,
                'delay' => 1000,
                'multiplier' => 2,
                'max_delay' => 0,
            ],
        ],
        'failed' => [
            'dsn' => 'typo3-db://',
            'options' => [
                'queue_name' => 'failed',
            ],
            'retry_strategy' => [
                'service' => null,
                'max_retries' => null,
                'delay' => null,
                'multiplier' => null,
                'max_delay' => null,
            ],
        ],
    ],
    'serializer' => [
        'default_serializer' => 'messenger.transport.native_php_serializer',
        'symfony_serializer' => [
            'format' => 'json',
            'context' => [],
        ],
    ],
    'routing' => [
        MyCommand::class => [
            'senders' => ['async'],
        ],
    ],
    'default_bus' => 'command.bus',
    'buses' => [
        'command.bus' => [
            'default_middleware' => [
                'enabled' => true,
            ],
        ],
        'query.bus' => [
            'default_middleware' => [
                'enabled' => true,
            ],
        ],
        'event.bus' => [
            'default_middleware' => [
                'enabled' => true,
                'allow_no_handlers' => true,
                'allow_no_senders' => true,
            ],
        ],
    ],
];
