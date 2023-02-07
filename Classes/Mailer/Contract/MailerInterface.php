<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Mailer\Contract;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;

interface MailerInterface extends \Symfony\Component\Mailer\MailerInterface
{
    public function getSentMessage(): ?SentMessage;

    public function getTransport(): TransportInterface;

    public function getRealTransport(): TransportInterface;
}
