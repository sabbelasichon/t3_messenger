<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
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

    /**
     * @return \Generator<string, mixed>
     */
    public function provideInvalidRetryStrategyConfigurations(): \Generator
    {
        yield 'Service option is defined along with other options' => [
            [
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
            ],
        ];

        yield 'Max retries option is invalid' => [
            [
                'transports' => [
                    'async' => [
                        'dsn' => 'foo://',
                        'retry_strategy' => [
                            'max_retries' => -1,
                        ],
                    ],
                ],
            ],
        ];

        yield 'Delay option is invalid' => [
            [
                'transports' => [
                    'async' => [
                        'dsn' => 'foo://',
                        'retry_strategy' => [
                            'delay' => -1,
                        ],
                    ],
                ],
            ],
        ];

        yield 'Multiplier option is invalid' => [
            [
                'transports' => [
                    'async' => [
                        'dsn' => 'foo://',
                        'retry_strategy' => [
                            'multiplier' => 0,
                        ],
                    ],
                ],
            ],
        ];

        yield 'Max delay option is invalid' => [
            [
                'transports' => [
                    'async' => [
                        'dsn' => 'foo://',
                        'retry_strategy' => [
                            'max_delay' => -1,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<mixed> $configuration
     *
     * @dataProvider provideInvalidRetryStrategyConfigurations
     */
    public function testThatAnExceptionIsThrownIfTheTransportsRetryStrategyConfigurationIsInvalid(
        array $configuration
    ): void {
        // Assert
        $this->expectException(InvalidOptionsException::class);

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

    public function testThatTheDefaultMiddlewareOptionIsNormalizedCorrectlyIfBoolean(): void
    {
        // Arrange
        $configuration = [
            'buses' => [
                'messenger.bus.default' => [
                    'default_middleware' => true,
                    'middleware' => [],
                ],
            ],
        ];

        // Act
        $resolvedConfiguration = $this->subject->resolve($configuration);

        // Assert
        self::assertEquals(
            [
                'messenger.bus.default' => [
                    'default_middleware' => [
                        'enabled' => true,
                        'allow_no_handlers' => false,
                        'allow_no_senders' => true,
                    ],
                    'middleware' => [],
                ],
            ],
            $resolvedConfiguration['buses']
        );
    }

    public function testThatTheDefaultMiddlewareOptionIsNormalizedCorrectlyIfStringWithValueAllowNoHandlers(): void
    {
        // Arrange
        $configuration = [
            'buses' => [
                'messenger.bus.default' => [
                    'default_middleware' => 'allow_no_handlers',
                    'middleware' => [],
                ],
            ],
        ];

        // Act
        $resolvedConfiguration = $this->subject->resolve($configuration);

        // Assert
        self::assertEquals(
            [
                'messenger.bus.default' => [
                    'default_middleware' => [
                        'enabled' => true,
                        'allow_no_handlers' => true,
                        'allow_no_senders' => true,
                    ],
                    'middleware' => [],
                ],
            ],
            $resolvedConfiguration['buses']
        );
    }

    public function testThatTheMiddlewareOptionIsNormalizedCorrectly(): void
    {
        // Arrange
        $configuration = [
            'default_bus' => 'foo',
            'buses' => [
                'foo' => [
                    'middleware' => ['App\Middleware\MyMiddleware', 'App\Middleware\AnotherMiddleware'],
                ],
                'baz' => [
                    'middleware' => [
                        [
                            'id' => 'App\Middleware\MyMiddleware',
                        ],
                    ],
                ],
                'bar' => [],
                'foo_bar' => [
                    'middleware' => [
                        [
                            'id' => 'doctrine_transaction',
                            'arguments' => ['custom'],
                        ],
                    ],
                ],
                'foo_baz' => [
                    'middleware' => [
                        [
                            'App\Middleware\MyMiddleware' => 'foo',
                        ],
                    ],
                ],
            ],
        ];

        // Act
        $resolvedConfiguration = $this->subject->resolve($configuration);

        // Assert
        self::assertEquals(
            [
                'foo' => [
                    'default_middleware' => [
                        'enabled' => true,
                        'allow_no_handlers' => false,
                        'allow_no_senders' => true,
                    ],
                    'middleware' => [
                        [
                            'id' => 'App\Middleware\MyMiddleware',
                        ],
                        [
                            'id' => 'App\Middleware\AnotherMiddleware',
                        ],
                    ],
                ],
                'baz' => [
                    'default_middleware' => [
                        'enabled' => true,
                        'allow_no_handlers' => false,
                        'allow_no_senders' => true,
                    ],
                    'middleware' => [
                        [
                            'id' => 'App\Middleware\MyMiddleware',
                        ],
                    ],
                ],
                'bar' => [
                    'default_middleware' => [
                        'enabled' => true,
                        'allow_no_handlers' => false,
                        'allow_no_senders' => true,
                    ],
                    'middleware' => [],
                ],
                'foo_bar' => [
                    'default_middleware' => [
                        'enabled' => true,
                        'allow_no_handlers' => false,
                        'allow_no_senders' => true,
                    ],
                    'middleware' => [
                        [
                            'id' => 'doctrine_transaction',
                            'arguments' => ['custom'],
                        ],
                    ],
                ],
                'foo_baz' => [
                    'default_middleware' => [
                        'enabled' => true,
                        'allow_no_handlers' => false,
                        'allow_no_senders' => true,
                    ],
                    'middleware' => [
                        [
                            'id' => 'App\Middleware\MyMiddleware',
                            'arguments' => 'foo',
                        ],
                    ],
                ],
            ],
            $resolvedConfiguration['buses']
        );
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
                        'default_middleware' => [
                            'enabled' => true,
                            'allow_no_handlers' => false,
                            'allow_no_senders' => true,
                        ],
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
