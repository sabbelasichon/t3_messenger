<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger;

final class CommandToHandlerMapper
{
    private array $mapping;

    public function __construct(array $mapping)
    {
        $this->mapping = $mapping;
    }

    public function commandToHandlerMapping(): array
    {
        $commandToHandlerMapping = [];

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
        if ($options === []) {
            return '';
        }

        $optionsMapping = [];
        foreach ($options as $key => $value) {
            $optionsMapping[] = $key . '=' . $value;
        }

        return ' (when ' . implode(', ', $optionsMapping) . ')';
    }

    /**
     * @phpstan-param class-string $class
     */
    private static function getClassDescription(string $class): string
    {
        try {
            $r = new \ReflectionClass($class);

            $docComment = $r->getDocComment();

            if (is_string($docComment) && $docComment !== '') {
                $docComments = preg_split('#\n\s*\*\s*[\n@]#', substr($docComment, 3, -2), 2);

                if ($docComments === false) {
                    return '';
                }

                $docComment = $docComments[0];

                $docComment = preg_replace('#\s*\n\s*\*\s*#', ' ', $docComment);

                if (! is_string($docComment)) {
                    return '';
                }

                return trim($docComment);
            }
        } catch (\ReflectionException) {
        }

        return '';
    }
}
