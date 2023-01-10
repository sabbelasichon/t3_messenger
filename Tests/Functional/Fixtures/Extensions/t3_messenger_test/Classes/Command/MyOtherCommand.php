<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Command;

final class MyOtherCommand
{
    private string $note;

    public function __construct(string $note)
    {
        $this->note = $note;
    }

    public function getNote(): string
    {
        return $this->note;
    }
}
