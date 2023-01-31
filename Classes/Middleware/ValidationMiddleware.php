<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Middleware;

use Ssch\T3Messenger\Exception\ValidationFailedException;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use TYPO3\CMS\Extbase\Validation\ValidatorResolver;

final class ValidationMiddleware implements MiddlewareInterface
{
    private ValidatorResolver $validatorResolver;

    public function __construct(ValidatorResolver $validatorResolver)
    {
        $this->validatorResolver = $validatorResolver;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();

        // Special case. We cannot do much about it, because the validation middleware can´t validate that type of message here
        if ($message instanceof SendEmailMessage) {
            return $stack->next()
                ->handle($envelope, $stack);
        }

        $validator = $this->validatorResolver->getBaseValidatorConjunction(\get_class($message));

        $errorResult = $validator->validate($message);

        if (\count($errorResult->getFlattenedErrors()) > 0) {
            throw new ValidationFailedException($message, $errorResult);
        }

        return $stack->next()
            ->handle($envelope, $stack);
    }
}
