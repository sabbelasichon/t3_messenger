<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class SiteStamp implements StampInterface
{
    private ?int $siteUid;

    public function __construct(?int $siteUid)
    {
        $this->siteUid = $siteUid;
    }

    public function getSiteUid(): ?int
    {
        return $this->siteUid;
    }
}
