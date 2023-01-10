<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Exception;

use Symfony\Component\Messenger\Exception\RuntimeException;
use TYPO3\CMS\Extbase\Error\Result;

final class ValidationFailedException extends RuntimeException
{
    private Result $violations;

    private object $violatingMessage;

    public function __construct(object $violatingMessage, Result $violations)
    {
        $this->violatingMessage = $violatingMessage;
        $this->violations = $violations;

        parent::__construct(sprintf('Message of type "%s" failed validation.', \get_class($this->violatingMessage)));
    }

    public function getViolatingMessage(): object
    {
        return $this->violatingMessage;
    }

    public function getViolations(): Result
    {
        return $this->violations;
    }
}
