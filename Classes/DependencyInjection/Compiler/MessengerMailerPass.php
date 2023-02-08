<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;
use TYPO3\CMS\Core\Mail\Event\AfterMailerSentMessageEvent;
use TYPO3\CMS\Core\Mail\Event\BeforeMailerSentMessageEvent;

final class MessengerMailerPass implements CompilerPassInterface
{
    private string $tagName;

    /**
     * @var array<class-string, class-string>
     */
    private array $additionalEvents = [
        BeforeMailerSentMessageEvent::class => \Ssch\T3Messenger\Mailer\Event\BeforeMailerSentMessageEvent::class,
        AfterMailerSentMessageEvent::class => \Ssch\T3Messenger\Mailer\Event\AfterMailerSentMessageEvent::class,
    ];

    public function __construct(string $tagName)
    {
        $this->tagName = $tagName;
    }

    public function process(ContainerBuilder $container): void
    {
        if (! $container->hasDefinition(ListenerProvider::class)) {
            // If there's no listener provider registered to begin with, don't bother registering listeners with it.
            return;
        }

        $listenerProviderDefinition = $container->findDefinition(ListenerProvider::class);

        $taggedServices = $container->findTaggedServiceIds($this->tagName);

        $eventsToCheck = array_keys($this->additionalEvents);

        foreach ($taggedServices as $id => $tags) {
            foreach ($tags as $eventListenerAttributes) {
                if (! isset($eventListenerAttributes['event'])) {
                    continue;
                }

                if (! in_array($eventListenerAttributes['event'], $eventsToCheck, true)) {
                    continue;
                }

                $eventName = $this->additionalEvents[$eventListenerAttributes['event']];

                $listenerProviderDefinition->addMethodCall('addListener', [
                    $eventName,
                    $id,
                    $eventListenerAttributes['method'] ?? null,
                ]);
            }
        }
    }
}
