<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Routing;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RequestContextAwareInterface;

final class RequestContextAwareFactory
{
    private HttpFoundationFactory $httpFoundationFactory;

    public function __construct(HttpFoundationFactory $httpFoundationFactory)
    {
        $this->httpFoundationFactory = $httpFoundationFactory;
    }

    public function create(): RequestContextAwareInterface
    {
        $requestContext = new RequestContext();

        if ($this->getRequest() !== null) {
            $requestContext->fromRequest($this->httpFoundationFactory->createRequest($this->getRequest()));
        }

        return new RequestContextService($requestContext);
    }

    private function getRequest(): ?ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'];
    }
}
