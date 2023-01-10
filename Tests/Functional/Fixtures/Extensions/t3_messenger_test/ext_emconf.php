<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

$EM_CONF['t3_messenger_test'] = [
    'title' => 'Test extension for Messenger',
    'description' => 'Test extension for Messenger',
    'category' => 'misc',
    'author' => 'Sebastian Schreiber',
    'author_email' => 'breakpoint@schreibersebastian.de',
    'state' => 'alpha',
    'internal' => '',
    'uploadfolder' => '0',
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'version' => '1.0.0',
    'autoload' => [
        'psr-4' => [
            'Ssch\\T3MessengerTest\\' => 'Classes',
        ],
    ],
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-12.9.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
