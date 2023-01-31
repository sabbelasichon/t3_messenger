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
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\PostgreSqlConnection;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\Schema\Types\EnumType;
use TYPO3\CMS\Core\Database\Schema\Types\SetType;

final class DoctrineTransportFactory implements TransportFactoryInterface
{
    /**
     * @var \Doctrine\DBAL\Connection[]
     */
    private static array $connections = [];

    private array $customDoctrineTypes = [
        EnumType::TYPE => EnumType::class,
        SetType::TYPE => SetType::class,
    ];

    /**
     * @param array<mixed> $options
     */
    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        $useNotify = ($options['use_notify'] ?? true);
        unset($options['transport_name'], $options['use_notify']);
        // Always allow PostgreSQL-specific keys, to be able to transparently fallback to the native driver when LISTEN/NOTIFY isn't available
        $configuration = PostgreSqlConnection::buildConfiguration($dsn, $options);

        try {
            $driverConnection = $this->getConnectionByName($configuration['connection']);

            if ($useNotify === true && $driverConnection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                $connection = new PostgreSqlConnection($configuration, $driverConnection);
            } else {
                $connection = new Connection($configuration, $driverConnection);
            }
        } catch (\Throwable $e) {
            throw new TransportException(sprintf(
                'Could not find Doctrine connection from Messenger DSN "%s".',
                $dsn
            ), 0, $e);
        }

        return new DoctrineTransport($connection, $serializer);
    }

    /**
     * @param array<mixed> $options
     */
    public function supports(string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'typo3-db://');
    }

    private function getConnectionByName(string $connectionName): \Doctrine\DBAL\Connection
    {
        if ($connectionName === '') {
            throw new TransportException('->getConnectionByName() requires a connection name to be provided.');
        }

        if (isset(self::$connections[$connectionName])) {
            return self::$connections[$connectionName];
        }

        $connectionParams = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections'][$connectionName] ?? [];
        if ($connectionParams === null || $connectionParams === []) {
            throw new TransportException(
                'The requested database connection named "' . $connectionName . '" has not been configured.',
            );
        }

        // Default to UTF-8 connection charset
        if (! isset($connectionParams['charset'])) {
            $connectionParams['charset'] = 'utf8';
        }

        // I got always an exception in the testing context: PDOException: SQLSTATE[HY093]: Invalid parameter number: parameter was not defined
        if (Environment::getContext()->isTesting()) {
            unset($connectionParams['wrapperClass']);
        }

        $connection = DriverManager::getConnection($connectionParams);

        // Register custom data types
        foreach ($this->customDoctrineTypes as $type => $className) {
            if (! Type::hasType($type)) {
                Type::addType($type, $className);
            }
        }

        // Register all custom data types in the type mapping
        foreach ($this->customDoctrineTypes as $type => $className) {
            $connection->getDatabasePlatform()
                ->registerDoctrineTypeMapping($type, $type);
        }

        self::$connections[$connectionName] = $connection;

        return self::$connections[$connectionName];
    }
}
