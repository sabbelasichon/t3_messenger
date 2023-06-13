<?php

call_user_func(static function () {
    if (! isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['t3_messenger']) || ! is_array(
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['t3_messenger']
    )) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['t3_messenger'] = [
            'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
            'backend' => \TYPO3\CMS\Core\Cache\Backend\SimpleFileBackend::class,
            'options' => [
                'defaultLifetime' => 0,
            ],
            'groups' => ['system'],
        ];
    }

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScriptSetup(
        "@import 'EXT:t3_messenger/Configuration/TypoScript/setup.typoscript'"
    );

    $iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);
    $iconRegistry->registerIcon(
        'tx-messenger-failed-messages-icon',
        \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        [
            'source' => 'EXT:t3_messenger/Resources/Public/Icons/failed-messages.svg',
        ]
    );
});
