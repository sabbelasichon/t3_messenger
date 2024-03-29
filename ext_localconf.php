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
});
