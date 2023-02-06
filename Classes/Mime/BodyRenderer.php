<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Mime;

use Ssch\T3Messenger\Mailer\MailValidityResolver;
use Symfony\Component\Mime\BodyRendererInterface;
use Symfony\Component\Mime\Message;
use TYPO3\CMS\Core\Mail\FluidEmail;

final class BodyRenderer implements BodyRendererInterface
{
    private MailValidityResolver $mailValidityResolver;

    public function __construct(MailValidityResolver $mailValidityResolver)
    {
        $this->mailValidityResolver = $mailValidityResolver;
    }

    public function render(Message $message): void
    {
        if (! $message instanceof FluidEmail) {
            return;
        }

        if ($message->getTextBody() !== null || $message->getHtmlBody() !== null) {
            // email has already been rendered
            return;
        }

        $this->mailValidityResolver->resolve($message);

        // Render the content
        $message->ensureValidity();
    }
}
