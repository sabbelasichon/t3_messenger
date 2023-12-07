<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Dashboard\Widgets\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ssch\T3Messenger\Dashboard\Widgets\Serializer\JsonSerializer;
use Ssch\T3Messenger\Repository\FailedMessageRepository;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\SingletonInterface;

final class FailedMessageController implements SingletonInterface
{
    private FailedMessageRepository $failedMessageRepository;

    private JsonSerializer $jsonSerializer;

    public function __construct(FailedMessageRepository $failedMessageRepository, JsonSerializer $jsonSerializer)
    {
        $this->failedMessageRepository = $failedMessageRepository;
        $this->jsonSerializer = $jsonSerializer;
    }

    public function deleteMessageAction(ServerRequestInterface $request): ResponseInterface
    {
        $messageSpecification = $this->jsonSerializer->decode($request->getBody()->__toString());
        $this->failedMessageRepository->removeMessage($messageSpecification);
        return new JsonResponse([
            'result' => 1,
        ]);
    }

    public function retryMessageAction(ServerRequestInterface $request): ResponseInterface
    {
        $messageSpecification = $this->jsonSerializer->decode($request->getBody()->__toString());
        $this->failedMessageRepository->retryMessage($messageSpecification);
        return new JsonResponse([
            'result' => 1,
        ]);
    }
}
