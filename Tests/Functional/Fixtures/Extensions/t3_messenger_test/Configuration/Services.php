<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

use Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Controller\MessengerController;
use Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\EventListener\BeforeMailerSentMessageEventListener;
use Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Handlers\MyMessengerHandler;
use Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Service\MyService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use TYPO3\CMS\Core\Mail\Event\BeforeMailerSentMessageEvent;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();
    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(MyService::class)->public();
    $services->set(MyMessengerHandler::class)->tag('messenger.message_handler');
    $services->set(BeforeMailerSentMessageEventListener::class)->tag('event.listener', [
        'event' => BeforeMailerSentMessageEvent::class,
    ]);
    $services->set(MessengerController::class);
};
