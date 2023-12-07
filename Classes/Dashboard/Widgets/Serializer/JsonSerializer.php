<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Dashboard\Widgets\Serializer;

use Ssch\T3Messenger\Dashboard\Widgets\Dto\MessageSpecification;

final class JsonSerializer
{
    public function decode(string $json): MessageSpecification
    {
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return MessageSpecification::fromArray($array);
    }
}
