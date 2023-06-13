<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\DependencyInjection\Compiler;

use Ssch\T3Messenger\Repository\FailedMessageRepository;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class FailureReceiverPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $failureMessageRepositoryDefinition = $container->getDefinition(FailedMessageRepository::class);
        $failedMessagesShowCommandDefinition = $container->getDefinition(
            'console.command.messenger_failed_messages_show'
        );
        $failureMessageRepositoryDefinition->replaceArgument(0, $failedMessagesShowCommandDefinition->getArgument(1));
    }
}
