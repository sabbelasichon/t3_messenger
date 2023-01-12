<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\ConfigurationModuleProvider;

use Ssch\T3Messenger\DependencyInjection\MessengerConfigurationCollector;
use Ssch\T3Messenger\DependencyInjection\MessengerConfigurationResolver;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Lowlevel\ConfigurationModuleProvider\AbstractProvider;

final class MessengerProvider extends AbstractProvider
{
    private MessengerConfigurationResolver $messengerConfigurationResolver;

    private PackageManager $packageManager;

    private array $mapping;

    public function __construct(
        MessengerConfigurationResolver $messengerConfigurationResolver,
        PackageManager $packageManager,
        array $mapping
    ) {
        $this->messengerConfigurationResolver = $messengerConfigurationResolver;
        $this->packageManager = $packageManager;
        $this->mapping = $mapping;
    }

    public function getConfiguration(): array
    {
        $config = (new MessengerConfigurationCollector($this->packageManager))->collect();

        return [
            'Messenger Configuration' => $this->messengerConfigurationResolver->resolve($config->getArrayCopy()),
            'Messenger Mapping' => $this->commandToHandlerMapping(),
        ];
    }

    private function commandToHandlerMapping()
    {
        $commandToHandlerMapping = [];
        #\TYPO3\CMS\Extbase\Utility\DebuggerUtility::var_dump($this->mapping);exit;
        foreach ($this->mapping as $bus => $handlersByMessage) {
            $messages = [];
            foreach ($handlersByMessage as $message => $handlers) {
                $messages[$message] = [];

                $messageDescription = self::getClassDescription($message);

                if ($messageDescription !== '') {
                    $messages[$message]['description'] = $messageDescription;
                }

                $messageHandledBy = [];
                foreach ($handlers as $handler) {
                    $handlerFormatted = $handler[0] . $this->formatConditions($handler[1]);
                    $messageHandledBy[$handlerFormatted] = [];

                    $handlerDescription = self::getClassDescription($handler[0]);
                    if ($handlerDescription !== '') {
                        $messageHandledBy[$handlerFormatted]['description'] = $handlerDescription;
                    }
                }

                if ($messageHandledBy !== []) {
                    $messages[$message]['handlers'] = $messageHandledBy;
                }
            }

            if ($messages !== []) {
                $commandToHandlerMapping[$bus]['messages'] = $messages;
            } else {
                $commandToHandlerMapping[$bus] = [sprintf('No handled message found in bus "%s".', $bus)];
            }
        }

        return $commandToHandlerMapping;
    }

    private function formatConditions(array $options): string
    {
        if (! $options) {
            return '';
        }

        $optionsMapping = [];
        foreach ($options as $key => $value) {
            $optionsMapping[] = $key . '=' . $value;
        }

        return ' (when ' . implode(', ', $optionsMapping) . ')';
    }

    private static function getClassDescription(string $class): string
    {
        try {
            $r = new \ReflectionClass($class);

            $docComment = $r->getDocComment();

            if ($docComment) {
                $docComment = preg_split('#\n\s*\*\s*[\n@]#', substr($docComment, 3, -2), 2)[0];

                return trim(preg_replace('#\s*\n\s*\*\s*#', ' ', $docComment));
            }
        } catch (\ReflectionException) {
        }

        return '';
    }
}
