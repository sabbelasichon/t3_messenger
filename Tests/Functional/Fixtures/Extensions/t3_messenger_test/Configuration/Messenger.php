<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

use Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Command\MyCommand;
use Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Command\MyFailingCommand;

return [
    'failure_transport' => 'failed',
    'default_bus' => 'command.bus',
    'transports' => [
        'async' => [
            'serializer' => 'messenger.transport.symfony_serializer',
            'dsn' => 'typo3-db://Default',
            'retry_strategy' => [
                'max_retries' => 0,
            ],
        ],
        'failed' => [
            'dsn' => 'typo3-db://Default?queue_name=failed',
        ],
        'sync' => [
            'dsn' => 'sync://',
        ],
    ],
    'routing' => [
        MyCommand::class => [
            'senders' => ['sync'],
        ],
        MyFailingCommand::class => [
            'senders' => ['sync'],
        ],
    ],
    'buses' => [
        'command.bus' => [
            'middleware' => [
                'validation' => [
                    'id' => 'validation',
                ],
                'logging' => [
                    'id' => 'logging',
                ],
                'server_request_context' => [
                    'id' => 'server_request_context',
                ],
            ],
        ],
        'query.bus' => [
            'default_middleware' => [
                'enabled' => true,
                'allow_no_handlers' => false,
                'allow_no_senders' => true,
            ],
        ],
    ],
];
