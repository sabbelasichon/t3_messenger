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
            'dsn' => 'typo3-db://Default',
        ],
        'failed' => [
            'dsn' => 'typo3-db://Default',
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

## Extbase Entities in Messages

If you need to pass an Extbase entity in a message, it's better to pass the entity's primary key (or whatever relevant information the handler actually needs, like email, etc.) instead of the object.

Have a look at the Symfony Documentation about [Doctrine Entities](https://symfony.com/doc/current/messenger.html#doctrine-entities-in-messages)


## Transport Configuration

Messenger supports a number of different transport types, each with their own options. Options can be passed to the transport via a DSN string or configuration.

Have a look at the Symfony Documentation about [Transports](https://symfony.com/doc/current/messenger.html#transport-configuration)

### Custom Doctrine Transport

The extension ships the [doctrine transport](https://symfony.com/doc/current/messenger.html#doctrine-transport) with a slightly modified configuration dsn.
Instead of using doctrine:// you have to use typo3-db://.

```php

return [
    'transports' => [
        'async' => [
            'dsn' => 'typo3-db://Default',
        ],
    ],
];

```

The format is typo3-db://<connection_name>, in case you have multiple connections and want to use one other than the "Default".
The transport will automatically create a table named messenger_messages.

Please have a look for further configuration details at the [doctrine transport](https://symfony.com/doc/current/messenger.html#doctrine-transport).

