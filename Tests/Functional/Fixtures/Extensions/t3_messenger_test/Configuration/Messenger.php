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
            'dsn' => 'typo3-db://default',
            'retry_strategy' => [
                'max_retries' => 0,
            ],
        ],
        'failed' => [
            'dsn' => 'typo3-db://default',
            'options' => [
                'queue_name' => 'failed',
            ],
        ],
    ],
    'routing' => [
        MyCommand::class => [
            'senders' => ['async'],
        ],
        MyFailingCommand::class => [
            'senders' => ['async'],
        ],
    ],
    'buses' => [
        'command.bus' => [
            'middleware' => [
                'id' => 'validation',
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
