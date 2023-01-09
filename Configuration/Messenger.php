<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_tactician" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

return [
    'failure_transport' => null,
    'transports' => [],
    'routing' => [],
    'default_bus' => 'command.bus',
    'buses' => [
        'command.bus' => [],
        'query.bus' => [],
        'event.bus' => [
            'default_middleware' => [
                'enabled' => true,
                'allow_no_handlers' => true,
                'allow_no_senders' => true,
            ],
        ],
    ],
];
