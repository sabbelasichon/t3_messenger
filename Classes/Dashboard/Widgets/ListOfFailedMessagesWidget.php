<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Dashboard\Widgets;

use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use TYPO3\CMS\Dashboard\Widgets\RequireJsModuleInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;

final class ListOfFailedMessagesWidget implements WidgetInterface, RequireJsModuleInterface
{
    private WidgetConfigurationInterface $configuration;

    private ListDataProviderInterface $dataProvider;

    private StandaloneView $view;

    private array $options;

    public function __construct(
        WidgetConfigurationInterface $configuration,
        ListDataProviderInterface $dataProvider,
        StandaloneView $view,
        array $options = []
    ) {
        $this->configuration = $configuration;
        $this->dataProvider = $dataProvider;
        $this->view = $view;
        $this->options = $options;
    }

    public function renderWidgetContent(): string
    {
        $this->view->setTemplate('Widget/ListOfFailedMessagesWidget');
        $this->view->assignMultiple([
            'configuration' => $this->configuration,
            'failedMessages' => $this->dataProvider->getItems(),
            'options' => $this->options,
        ]);

        return $this->view->render();
    }

    public function getOptions(): array
    {
        return $this->options;
    }


    public function getRequireJsModules(): array
    {
        return [
            'TYPO3/CMS/T3Messenger/FailedMessages',
        ];
    }
}
