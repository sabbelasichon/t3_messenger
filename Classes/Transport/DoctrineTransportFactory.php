<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
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
use TYPO3\CMS\Core\Core\Environment;

final class DoctrineTransportFactory implements TransportFactoryInterface
{
    /**
     * @param array<mixed> $options
     */
    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        $databaseConfiguration = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default'];

        // I got always an exception in the testing context: PDOException: SQLSTATE[HY093]: Invalid parameter number: parameter was not defined
        if (Environment::getContext()->isTesting()) {
            unset($databaseConfiguration['wrapperClass']);
        }

        $connection = DriverManager::getConnection($databaseConfiguration);
        $doctrineTransportConnection = new Connection($options, $connection);

        return new DoctrineTransport($doctrineTransportConnection, $serializer);
    }

    /**
     * @param array<mixed> $options
     */
    public function supports(string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'typo3-db://');
    }
}
