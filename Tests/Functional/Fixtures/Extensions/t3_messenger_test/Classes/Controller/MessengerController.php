<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Controller;

use Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Command\MyCommand;
use Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Service\MyService;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class MessengerController extends ActionController
{
    private MyService $myService;

    public function __construct(MyService $myService)
    {
        $this->myService = $myService;
    }

    public function dispatchAction()
    {
        $this->myService->dispatch(new MyCommand('max.mustermann@domain.com'));
    }
}
