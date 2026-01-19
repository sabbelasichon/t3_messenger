<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Transport;

use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Psr\EventDispatcher\EventDispatcherInterface;
use Ssch\T3Messenger\Event\PreRejectEvent;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\SetupableTransportInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class DoctrineTransportWrapper implements TransportInterface, SetupableTransportInterface, MessageCountAwareInterface, ListableReceiverInterface
{
    private DoctrineTransport $doctrineTransport;

    private DBALConnection $driverConnection;

    private array $configuration;

    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        DoctrineTransport $doctrineTransport,
        array $configuration,
        DBALConnection $driverConnection,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->doctrineTransport = $doctrineTransport;
        $this->configuration = $configuration;
        $this->driverConnection = $driverConnection;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function setup(): void
    {
        $this->doctrineTransport->setup();
    }

    public function all(int $limit = null): iterable
    {
        return $this->doctrineTransport->all($limit);
    }

    /**
     * @param mixed $id
     */
    public function find($id): ?Envelope
    {
        return $this->doctrineTransport->find($id);
    }

    public function getMessageCount(): int
    {
        return $this->doctrineTransport->getMessageCount();
    }

    public function get(): iterable
    {
        return $this->doctrineTransport->get();
    }

    public function ack(Envelope $envelope): void
    {
        $this->doctrineTransport->ack($envelope);
    }

    public function reject(Envelope $envelope): void
    {
        $this->eventDispatcher->dispatch(new PreRejectEvent($envelope));
        $this->doctrineTransport->reject($envelope);
    }

    public function send(Envelope $envelope): Envelope
    {
        return $this->doctrineTransport->send($envelope);
    }

    public function getSql(): AdditionalTransportTable
    {
        $schemaManager = $this->driverConnection->createSchemaManager();

        $tableName = $this->configuration['table_name'];

        if (! $schemaManager->tablesExist([$tableName])) {
            $table = $this->addTableToSchema($schemaManager->introspectSchema());
        } else {
            $table = $schemaManager->introspectTable($tableName);
        }

        return new AdditionalTransportTable($tableName, $this->buildSchemaTableSQL(
            $this->removeColumnCollations($table)
        ));
    }

    private function buildSchemaTableSQL(Table $table): string
    {
        $platform = $this->driverConnection->getDatabasePlatform();

        return $platform->getCreateTableSQL($table)[0];
    }

    private function removeColumnCollations(Table $table): Table
    {
        foreach (['queue_name', 'headers', 'body'] as $columnName) {
            if (! $table->hasColumn($columnName)) {
                continue;
            }

            $table->getColumn($columnName)
                ->setPlatformOptions([]);
        }

        return $table;
    }

    private function addTableToSchema(Schema $schema): Table
    {
        $table = $schema->createTable($this->configuration['table_name']);
        // add an internal option to mark that we created this & the non-namespaced table name
        $table->addOption('_symfony_messenger_table_name', $this->configuration['table_name']);
        $table->addColumn('id', Types::INTEGER)
            ->setAutoincrement(true)
            ->setUnsigned(true)
            ->setNotnull(true);
        $table->addColumn('body', Types::TEXT)
            ->setNotnull(true);
        $table->addColumn('headers', Types::TEXT)
            ->setNotnull(true);
        $table->addColumn('queue_name', Types::STRING)
            ->setLength(190) // MySQL 5.6 only supports 191 characters on an indexed column in utf8mb4 mode
            ->setNotnull(true);
        $table->addColumn('created_at', Types::DATETIME_MUTABLE)
            ->setNotnull(true);
        $table->addColumn('available_at', Types::DATETIME_MUTABLE)
            ->setNotnull(true);
        $table->addColumn('delivered_at', Types::DATETIME_MUTABLE)
            ->setNotnull(false);
        $table->addPrimaryKeyConstraint(
            new PrimaryKeyConstraint(null, [new UnqualifiedName(Identifier::unquoted('id'))], true)
        );
        $table->addIndex(['queue_name']);
        $table->addIndex(['available_at']);
        $table->addIndex(['delivered_at']);

        return $table;
    }
}
