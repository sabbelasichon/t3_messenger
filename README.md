# TYPO3 Symfony messenger adapter
Integrates Symfony Messenger into TYPO3
[https://symfony.com/doc/current/components/messenger.html](https://symfony.com/doc/current/components/messenger.html)

## Integration guide

The extension basically provides the same functionality as if you would use the messenger in the Symfony Framework.
In order to configure the messenger you have to put a Messenger.php file under the Configuration of an extension.

```php

return [
    'failure_transport' => 'failed',
    'default_bus' => 'command.bus',
    'transports' => [
        'async' => [
            'dsn' => 'typo3-db://default',
        ],
        'failed' => [
            'dsn' => 'typo3-db://default',
            'options' => [
                'queue_name' => 'failed',
            ],
        ],
    ],
    'routing' => [
        MyCommand::class => ['senders' => ['async']],
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

```