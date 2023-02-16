<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Symfony Messenger Wrapper',
    'description' => 'Symfony Messenger Wrapper',
    'category' => 'misc',
    'author' => 'Sebastian Schreiber',
    'author_email' => 'breakpoint@schreibersebastian.de',
    'state' => 'beta',
    'internal' => '',
    'uploadfolder' => '0',
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'version' => '1.2.1',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-12.9.99',
            'typo3_psr_cache_adapter' => '1.0.0-1.9.99'
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'Ssch\\T3Messenger\\' => 'Classes',
        ],
    ],
    'autoload-dev' => [
        'psr-4' => [
            'Ssch\\T3Messenger\\Tests\\' => 'Tests',
        ],
    ],
];
