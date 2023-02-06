<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Transport;

final class AdditionalTransportTable
{
    private string $tableName;

    private string $sql;

    public function __construct(string $tableName, string $sql)
    {
        $this->tableName = $tableName;
        $this->sql = $sql;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function getSql(): string
    {
        return $this->sql;
    }
}
