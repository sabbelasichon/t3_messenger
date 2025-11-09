<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Tests\Functional;

use Ssch\T3Messenger\Event\PreRejectEvent;
use Ssch\T3Messenger\Exception\ValidationFailedException;
use Ssch\T3Messenger\Stamp\SiteStamp;
use Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Command\MyCommand;
use Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Command\MyFailingCommand;
use Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Command\MyOtherCommand;
use Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\EventListener\PreRejectEventListener;
use Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Service\MyService;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\EventListener\StopWorkerOnFailureLimitListener;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Worker;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class MessengerTest extends FunctionalTestCase
{
    private const ROOT_PAGE_UID = 1;

    private Site $site;

    protected function setUp(): void
    {
        $this->pathsToLinkInTestInstance = [
            'typo3conf/ext/t3_messenger/Tests/Functional/Fixtures/sites' => 'typo3conf/sites',
        ];
        $this->testExtensionsToLoad = [
            'typo3conf/ext/typo3_psr_cache_adapter',
            'typo3conf/ext/t3_messenger',
            'typo3conf/ext/t3_messenger/Tests/Functional/Fixtures/Extensions/t3_messenger_test',
        ];

        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/pages.csv');
        $this->setUpFrontendRootPage(
            self::ROOT_PAGE_UID,
            ['EXT:t3_messenger/Tests/Functional/Fixtures/Configuration/TypoScript/Basic.typoscript']
        );

        $this->site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByRootPageId(self::ROOT_PAGE_UID);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create('en');
    }

    public function testThatCommandIsRoutedToAsyncTransportSuccessfully(): void
    {
        $this->get(MyService::class)->dispatch(new MyCommand('max.mustermann@domain.com'));

        /** @var TransportInterface $transport */
        $transport = $this->get('messenger.transport.async');
        self::assertCount(1, $transport->get());
    }

    public function testThatInvalidCommandThrowsAValidationException(): void
    {
        $this->expectException(ValidationFailedException::class);

        $this->get(MyService::class)->dispatch(new MyCommand('notvalidemail'));
    }

    public function testThatCommandIsHandledSynchronously(): void
    {
        $envelope = $this->get(MyService::class)->dispatch(new MyOtherCommand('note'));
        $handledStamps = $envelope->all(HandledStamp::class);
        self::assertCount(1, $handledStamps);
    }

    public function testThatFailingCommandIsTransferredToFailureTransport(): void
    {
        $this->get(MyService::class)->dispatch(new MyFailingCommand('note'));

        $receivers = [
            'async' => $this->get('messenger.transport.async'),
        ];

        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->get('event_dispatcher');
        $eventDispatcher->addSubscriber(new StopWorkerOnFailureLimitListener(1));

        $worker = new Worker($receivers, $this->get('command.bus'), $eventDispatcher);
        $worker->run();

        /** @var TransportInterface $transport */
        $transport = $this->get('messenger.transport.failed');
        self::assertCount(1, $transport->get());
    }

    public function testThatOnRejectAnPreRejectEventIsDispatched(): void
    {
        $this->get(MyService::class)->dispatch(new MyFailingCommand('note'));

        $receivers = [
            'async' => $this->get('messenger.transport.async'),
        ];

        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->get('event_dispatcher');
        $eventDispatcher->addSubscriber(new StopWorkerOnFailureLimitListener(1));

        $worker = new Worker($receivers, $this->get('command.bus'), $eventDispatcher);
        $worker->run();

        /** @var TransportInterface $transport */
        $transport = $this->get('messenger.transport.failed');
        foreach ($transport->get() as $message) {
            $transport->reject($message);
        }

        $preRejectEventListener = $this->get(PreRejectEventListener::class);

        $events = array_filter(
            $preRejectEventListener->getEvents(),
            fn (PreRejectEvent $event) => $event->getEnvelope()
                ->last(SentToFailureTransportStamp::class) !== null
        );

        self::assertCount(1, $events);

    }

    public function testThatServerRequestContextMiddlewareIsDefinedCorrectly(): void
    {
        $uri = new Uri($this->site->getBase()->__toString() . '/');

        $this->executeFrontendSubRequest(new InternalRequest($uri->__toString()));

        /** @var TransportInterface $transport */
        $transport = $this->get('messenger.transport.async');
        $envelopes = $transport->get();

        $firstEnvelope = null;
        foreach ($envelopes as $envelope) {
            $firstEnvelope = $envelope;
            break;
        }

        self::assertNotEmpty($firstEnvelope);
        $siteStamp = $firstEnvelope->last(SiteStamp::class);

        self::assertCount(1, $envelopes);
        self::assertInstanceOf(SiteStamp::class, $siteStamp);
        self::assertSame($siteStamp->getSiteUid(), $this->site->getRootPageId());
    }
}
