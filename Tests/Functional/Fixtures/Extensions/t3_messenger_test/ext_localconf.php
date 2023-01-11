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

defined('TYPO3_MODE') || die('Access denied.');

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'T3MessengerTest',
    'Messenger',
    [
        \Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Controller\MessengerController::class => 'dispatch',
    ],
    [
        \Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Controller\MessengerController::class => 'dispatch',
    ]
);
$GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger'] = array_replace_recursive(
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger'] ?? [],
    [
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
                    'validation' => [
                        'id' => 'validation',
                    ],
                    'router_context' => [
                        'id' => 'router_context',
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

    ]
);
