<?php

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
