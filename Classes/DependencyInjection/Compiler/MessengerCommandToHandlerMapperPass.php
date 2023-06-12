<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\DependencyInjection\Compiler;

use Ssch\T3Messenger\CommandToHandlerMapper;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class MessengerCommandToHandlerMapperPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (! $container->hasDefinition('console.command.messenger_debug')) {
            return;
        }

        if (! $container->hasDefinition(CommandToHandlerMapper::class)) {
            return;
        }

        // steal configurations already done by the MessengerPass so we dont have to duplicate the work
        // as approved by @ryanweaver with the "I've seen Nicolas do worse" certificate
        // @sebastian schreiber: Love this comment
        $commandToHandlerMapper = $container->getDefinition(CommandToHandlerMapper::class);

        $consumeCommandDefinition = $container->getDefinition('console.command.messenger_debug');
        $mapping = $consumeCommandDefinition->getArgument(0);
        $commandToHandlerMapper->replaceArgument(0, $mapping);
    }
}
