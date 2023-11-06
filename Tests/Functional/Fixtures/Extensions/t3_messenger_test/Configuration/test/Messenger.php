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
use Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Command\MyOtherFailingCommand;

return [
    'routing' => [
        MyCommand::class => [
            'senders' => ['async'],
        ],
        MyFailingCommand::class => [
            'senders' => ['async'],
        ],
        MyOtherFailingCommand::class => [
            'senders' => ['async'],
        ],
    ],
];
