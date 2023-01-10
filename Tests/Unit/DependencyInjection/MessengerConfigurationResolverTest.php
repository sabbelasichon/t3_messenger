<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_tactician" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Ssch\T3Messenger\DependencyInjection\MessengerConfigurationResolver;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

final class MessengerConfigurationResolverTest extends TestCase
{
    private MessengerConfigurationResolver $subject;

    protected function setUp(): void
    {
        $this->subject = new MessengerConfigurationResolver();
    }

    public function testThatAnExceptionIsThrownIfTheTransportsRetryStrategyConfigurationIsInvalid(): void
    {
        // Assert
        $this->expectException(InvalidOptionsException::class);

        // Arrange
        $configuration = [
            'transports' => [
                'async' => [
                    'dsn' => 'foo://',
                    'retry_strategy' => [
                        'service' => 'foo',
                        'max_retries' => 1,
                        'delay' => 1,
                        'multiplier' => 1,
                        'max_delay' => 1,
                    ],
                ],
            ],
        ];

        // Act
        $this->subject->resolve($configuration);
    }

    public function testThatAnExceptionIsThrownIfNoDefaultBusIsDefinedForMultipleBuses(): void
    {
        // Assert
        $this->expectException(InvalidOptionsException::class);

        // Arrange
        $configuration = [
            'buses' => [
                'command.bus' => [],
                'query.bus' => [],
            ],
        ];

        // Act
        $this->subject->resolve($configuration);
    }

    public function testThatAnExceptionIsThrownIfTheDefinedDefaultBusIsNotDefinedInBuses(): void
    {
        // Assert
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage(
            'The specified default bus "foo" is not configured. Available buses are "command.bus", "query.bus".'
        );

        // Arrange
        $configuration = [
            'default_bus' => 'foo',
            'buses' => [
                'command.bus' => [],
                'query.bus' => [],
            ],
        ];

        // Act
        $this->subject->resolve($configuration);
    }

    public function testThatDefaultsAreConfiguredCorrectly(): void
    {
        // Arrange
        $configuration = [];

        // Act
        $resolvedConfiguration = $this->subject->resolve($configuration);

        // Assert
        self::assertEquals(
            [
                'serializer' => [
                    'default_serializer' => 'messenger.transport.native_php_serializer',
                    'symfony_serializer' => [
                        'format' => 'json',
                        'context' => [],
                    ],
                ],
                'routing' => [],
                'default_bus' => null,
                'buses' => [
                    'messenger.bus.default' => [
                        'default_middleware' => true,
                        'middleware' => [],
                    ],
                ],
                'failure_transport' => null,
                'transports' => [],
            ],
            $resolvedConfiguration
        );
    }
}
