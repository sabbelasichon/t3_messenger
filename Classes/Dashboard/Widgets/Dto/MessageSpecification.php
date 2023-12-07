<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Dashboard\Widgets\Dto;

use Webmozart\Assert\Assert;

final class MessageSpecification
{
    /**
     * @var int|string
     */
    private $id;

    private string $transport;

    /**
     * @param string|int $id
     */
    private function __construct($id, string $transport)
    {
        $this->id = $id;
        $this->transport = $transport;
    }

    public static function fromArray(array $array): self
    {
        Assert::keyExists($array, 'id');
        Assert::keyExists($array, 'transport');

        return new self($array['id'], $array['transport']);
    }

    /**
     * @return string|int
     */
    public function getId()
    {
        return $this->id;
    }

    public function getTransport(): string
    {
        return $this->transport;
    }
}
