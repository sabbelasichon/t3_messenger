<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_tactician" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Transport;

use Doctrine\DBAL\DriverManager;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class DoctrineTransportFactory implements TransportFactoryInterface
{
    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        $connection = DriverManager::getConnection($GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']);
        $doctrineTransportConnection = new Connection($options, $connection);

        return new DoctrineTransport($doctrineTransportConnection, $serializer);
    }

    public function supports(string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'typo3-db://');
    }
}
