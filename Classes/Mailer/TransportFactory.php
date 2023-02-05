<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Mailer;

use Symfony\Component\Mailer\Transport\TransportInterface;

final class TransportFactory
{
    private \TYPO3\CMS\Core\Mail\TransportFactory $transportFactory;

    public function __construct(\TYPO3\CMS\Core\Mail\TransportFactory $transportFactory)
    {
        $this->transportFactory = $transportFactory;
    }

    public function get(): TransportInterface
    {
        $mailSettings = (array) $GLOBALS['TYPO3_CONF_VARS']['MAIL'];
        unset($mailSettings['transport_spool_type']);

        return $this->transportFactory->get($mailSettings);
    }
}
