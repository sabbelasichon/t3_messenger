<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_tactician" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

use Psr\EventDispatcher\EventDispatcherInterface;
use Ssch\T3Messenger\DependencyInjection\Compiler\T3MessengerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use Symfony\Component\Messenger\Command\DebugCommand;
use Symfony\Component\Messenger\DependencyInjection\MessengerPass;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\Middleware\AddBusNameStampMiddleware;
use Symfony\Component\Messenger\Middleware\DispatchAfterCurrentBusMiddleware;
use Symfony\Component\Messenger\Middleware\FailedMessageProcessingMiddleware;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\RejectRedeliveredMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\Normalizer\FlattenExceptionNormalizer;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

return static function (ContainerConfigurator $containerConfigurator, ContainerBuilder $containerBuilder): void {
    $services = $containerConfigurator->services();
    $services->defaults()
        ->public()
        ->autowire()
        ->autoconfigure();

    $services->load('Ssch\\T3Messenger\\', __DIR__ . '/../Classes/')->exclude([__DIR__ . '/../Classes/Command']);

    $services
        ->alias('messenger.default_serializer', 'messenger.transport.native_php_serializer')
        ->alias(SerializerInterface::class, 'messenger.default_serializer')

        // Asynchronous
        ->set('messenger.senders_locator', SendersLocator::class)
        ->args([abstract_arg('per message senders map'), abstract_arg('senders service locator')])
        ->set('messenger.middleware.send_message', SendMessageMiddleware::class)
        ->args([service('messenger.senders_locator'), service(EventDispatcherInterface::class)])

        // Message encoding/decoding
        #->set('messenger.transport.symfony_serializer', Serializer::class)
        #->args([service('serializer'), abstract_arg('format'), abstract_arg('context')])

        #->set('serializer.normalizer.flatten_exception', FlattenExceptionNormalizer::class)
        #->tag('serializer.normalizer', ['priority' => -880])

        ->set('messenger.transport.native_php_serializer', PhpSerializer::class)
        // Middleware
        ->set('messenger.middleware.handle_message', HandleMessageMiddleware::class)
        ->abstract()
        ->args([abstract_arg('bus handler resolver')])
        ->set('messenger.middleware.add_bus_name_stamp_middleware', AddBusNameStampMiddleware::class)
        ->abstract()
        ->set('messenger.middleware.dispatch_after_current_bus', DispatchAfterCurrentBusMiddleware::class)
        ->set('messenger.middleware.reject_redelivered_message_middleware', RejectRedeliveredMessageMiddleware::class)
        ->set('messenger.middleware.failed_message_processing_middleware', FailedMessageProcessingMiddleware::class);

    $services->set('console.command.messenger_debug', DebugCommand::class)
        ->args([
            [], // Message to handlers mapping
        ])
        ->tag('console.command', [
            'command' => 't3_messenger:debug',
        ]);

    $containerBuilder->registerForAutoconfiguration(MessageHandlerInterface::class)
        ->addTag('messenger.message_handler');

    $containerBuilder->addCompilerPass(new T3MessengerPass());
    $containerBuilder->addCompilerPass(new MessengerPass());
};
