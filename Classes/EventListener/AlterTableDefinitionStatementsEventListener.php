<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\EventListener;

use Psr\Container\ContainerInterface;
use Ssch\T3Messenger\Transport\DoctrineTransportWrapper;
use TYPO3\CMS\Core\Database\Event\AlterTableDefinitionStatementsEvent;

final class AlterTableDefinitionStatementsEventListener
{
    private ContainerInterface $transportLocator;

    private array $transportNames;

    public function __construct(ContainerInterface $transportLocator, array $transportNames = [])
    {
        $this->transportLocator = $transportLocator;
        $this->transportNames = $transportNames;
    }

    public function __invoke(AlterTableDefinitionStatementsEvent $event): void
    {
        $additionalStatements = [];
        foreach ($this->transportNames as $transportName) {
            $transport = $this->transportLocator->get($transportName);
            if ($transport instanceof DoctrineTransportWrapper) {
                $additionalTable = $transport->getSql();
                if (! array_key_exists($additionalTable->getTableName(), $additionalStatements)) {
                    $additionalStatements[$additionalTable->getTableName()] = $additionalTable->getSql() . ';';
                }
            }
        }

        if ($additionalStatements === []) {
            return;
        }

        $event->addSqlData(implode(' ', $additionalStatements));
    }
}
