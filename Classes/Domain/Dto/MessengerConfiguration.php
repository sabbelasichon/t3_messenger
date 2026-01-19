<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Domain\Dto;

/**
 * @extends \ArrayObject<string, string>
 */
final class MessengerConfiguration extends \ArrayObject
{
    /**
     * @var string[]
     */
    private array $extensions = [];

    public function addExtension(string $extension): void
    {
        $this->extensions[$extension] = $extension;
    }

    /**
     * @return string[]
     */
    public function getExtensions(): array
    {
        return $this->extensions;
    }
}
