<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Ssch\T3Messenger\Stamp\SiteStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\SiteFinder;

final class ServerRequestContextMiddleware implements MiddlewareInterface
{
    private SiteFinder $siteFinder;

    public function __construct(SiteFinder $siteFinder)
    {
        $this->siteFinder = $siteFinder;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $siteStamp = $envelope->last(SiteStamp::class);
        if ($envelope->last(ConsumedByWorkerStamp::class) === null || ! $siteStamp instanceof SiteStamp) {
            if ($this->getRequest() === null) {
                $envelope = $envelope->with(new SiteStamp(null));

                return $stack->next()
                    ->handle($envelope, $stack);
            }

            $site = $this->getRequest()
                ->getAttribute('site');
            $siteUid = $site instanceof SiteInterface ? $site->getRootPageId() : null;

            $envelope = $envelope->with(new SiteStamp($siteUid));

            return $stack->next()
                ->handle($envelope, $stack);
        }

        $currentServerRequest = $this->getRequest();

        if ($siteStamp->getSiteUid() === null) {
            return $stack->next()
                ->handle($envelope, $stack);
        }

        try {
            $site = $this->siteFinder->getSiteByRootPageId($siteStamp->getSiteUid());
        } catch (SiteNotFoundException $siteNotFoundException) {
            return $stack->next()
                ->handle($envelope, $stack);
        }

        $normalizedParams = new NormalizedParams(
            [
                'HTTP_HOST' => $site->getBase()
                    ->getHost(),
                'HTTPS' => $site->getBase()
                    ->getScheme() === 'https' ? 'on' : 'off',
            ],
            $GLOBALS['TYPO3_CONF_VARS']['SYS'],
            '',
            ''
        );

        $serverRequest = (new ServerRequest())
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('normalizedParams', $normalizedParams)
            ->withAttribute('site', $site);

        $GLOBALS['TYPO3_REQUEST'] = $serverRequest;

        try {
            return $stack->next()
                ->handle($envelope, $stack);
        } finally {
            $GLOBALS['TYPO3_REQUEST'] = $currentServerRequest;
        }
    }

    private function getRequest(): ?ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'];
    }
}
