<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\DependencyInjection;

use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class MessengerConfigurationResolver
{
    public function resolve(array $configuration): array
    {
        $resolver = new OptionsResolver();
        $this->configureDefaultOptions($resolver);

        $resolvedConfiguration = $resolver->resolve($configuration);

        $this->validateBusConfiguration($resolvedConfiguration);
        $this->validateTransportsConfiguration($resolvedConfiguration);

        return $resolvedConfiguration;
    }

    private function configureDefaultOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('serializer', function (OptionsResolver $serializerResolver) {
            $serializerResolver
                ->define('default_serializer')
                ->default('messenger.transport.native_php_serializer')
                ->info('Service id to use as the default serializer for the transports.');

            $serializerResolver->setDefault(
                'symfony_serializer',
                function (OptionsResolver $symfonySerializerResolver) {
                    $symfonySerializerResolver
                        ->define('format')
                        ->default('json')
                        ->info(
                            'Serialization format for the messenger.transport.symfony_serializer service (which is not the serializer used by default).'
                        );

                    $symfonySerializerResolver
                        ->define('context')
                        ->default([])
                        ->info(
                            'Context array for the messenger.transport.symfony_serializer service (which is not the serializer used by default).'
                        );
                }
            );
        });

        $resolver
            ->define('failure_transport')
            ->default(null)
            ->info('Transport name to send failed messages to (after all retries have failed).');

        $resolver->setDefaults([
            'routing' => [],
            'default_bus' => null,
        ]);

        $resolver
            ->define('buses')
            ->default([
                'messenger.bus.default' => [
                    'default_middleware' => [
                        'enabled' => true,
                        'allow_no_handlers' => false,
                        'allow_no_senders' => true,
                    ],
                    'middleware' => [],
                ],
            ])
            ->normalize(function (Options $options, $value) {
                foreach ($value as &$busConfiguration) {
                    if (! isset($busConfiguration['default_middleware'])) {
                        $busConfiguration['default_middleware'] = [
                            'enabled' => true,
                            'allow_no_handlers' => false,
                            'allow_no_senders' => true,
                        ];
                        continue;
                    }

                    if (! is_string($busConfiguration['default_middleware']) && ! is_bool(
                        $busConfiguration['default_middleware']
                    )) {
                        continue;
                    }

                    if (\is_string(
                        $busConfiguration['default_middleware']
                    ) && $busConfiguration['default_middleware'] === 'allow_no_handlers') {
                        $busConfiguration['default_middleware'] = [
                            'enabled' => true,
                            'allow_no_handlers' => true,
                            'allow_no_senders' => true,
                        ];

                        continue;
                    }

                    $busConfiguration['default_middleware'] = [
                        'enabled' => $busConfiguration['default_middleware'],
                        'allow_no_handlers' => false,
                        'allow_no_senders' => true,
                    ];
                }

                foreach ($value as &$busConfiguration) {
                    if (! isset($busConfiguration['middleware'])) {
                        $busConfiguration['middleware'] = [];
                        continue;
                    }

                    if (\is_string($busConfiguration['middleware'])) {
                        $busConfiguration['middleware'] = [$busConfiguration['middleware']];
                        continue;
                    }

                    foreach ($busConfiguration['middleware'] as $key => $middleware) {
                        if (! \is_array($middleware)) {
                            $busConfiguration['middleware'][$key] = [
                                'id' => $middleware,
                            ];
                            continue;
                        }

                        if (isset($middleware['id'])) {
                            continue;
                        }

                        if (\count($middleware) > 1) {
                            throw new \InvalidArgumentException(
                                'Invalid middleware: a map with a single factory id as key and its arguments as value was expected, ' . json_encode(
                                    $middleware
                                ) . ' given.'
                            );
                        }

                        $busConfiguration['middleware'][$key] = [
                            'id' => key($middleware),
                            'arguments' => current($middleware),
                        ];
                    }
                }

                return $value;
            });

        $resolver->setDefault('transports', function (OptionsResolver $transportResolver) {
            $transportResolver
                ->setPrototype(true)
                ->setRequired(['dsn'])
                ->setDefaults([
                    'options' => [],
                ]);

            $transportResolver
                ->define('serializer')
                ->default(null)
                ->info('Service id of a custom serializer to use.');

            $transportResolver
                ->define('rate_limiter')
                ->default(null)
                ->info('Rate limiter name to use when processing messages');

            $transportResolver
                ->define('failure_transport')
                ->default(null)
                ->info('Transport name to send failed messages to (after all retries have failed).');

            $transportResolver->setDefault('retry_strategy', function (OptionsResolver $retryStrategyResolver) {
                $retryStrategyResolver->setDefaults([
                    'service' => null,
                ]);

                $retryStrategyResolver
                    ->define('max_retries')
                    ->default(3)
                    ->allowedValues(function ($value) {
                        return is_int($value) && $value >= 0;
                    })
                    ->allowedTypes('integer');

                $retryStrategyResolver
                    ->define('delay')
                    ->default(1000)
                    ->allowedTypes('integer')
                    ->allowedValues(function ($value) {
                        return is_int($value) && $value >= 0;
                    })
                    ->info('Time in ms to delay (or the initial value when multiplier is used)');

                $retryStrategyResolver
                    ->define('multiplier')
                    ->default(2)
                    ->allowedTypes('float', 'integer')
                    ->allowedValues(function ($value) {
                        return is_int($value) && $value > 0;
                    })
                    ->info(
                        'If greater than 1, delay will grow exponentially for each retry: this delay = (delay * (multiple ^ retries))'
                    );

                $retryStrategyResolver
                    ->define('max_delay')
                    ->default(0)
                    ->allowedTypes('integer')
                    ->allowedValues(function ($value) {
                        return is_int($value) && $value >= 0;
                    })
                    ->info('Max time in ms that a retry should ever be delayed (0 = infinite)');
            });
        });
    }

    private function validateBusConfiguration(array $resolvedConfiguration): void
    {
        if (isset($resolvedConfiguration['buses']) && \count(
            $resolvedConfiguration['buses']
        ) > 1 && $resolvedConfiguration['default_bus'] === null) {
            throw new InvalidOptionsException('You must specify the "default_bus" if you define more than one bus.');
        }

        if (isset($resolvedConfiguration['buses']) && $resolvedConfiguration['default_bus'] !== null && ! isset($resolvedConfiguration['buses'][$resolvedConfiguration['default_bus']])) {
            throw new InvalidOptionsException(sprintf(
                'The specified default bus "%s" is not configured. Available buses are "%s".',
                $resolvedConfiguration['default_bus'],
                implode('", "', array_keys($resolvedConfiguration['buses']))
            ));
        }
    }

    private function validateTransportsConfiguration(array $resolvedConfiguration): void
    {
        foreach ($resolvedConfiguration['transports'] as $transport) {
            if (! isset($transport['retry_strategy'])) {
                continue;
            }

            if (isset($transport['retry_strategy']['service']) && (isset($transport['retry_strategy']['max_retries']) || isset($transport['retry_strategy']['delay']) || isset($transport['retry_strategy']['multiplier']) || isset($transport['retry_strategy']['max_delay']))) {
                throw new InvalidOptionsException(
                    'The "service" cannot be used along with the other "retry_strategy" options.'
                );
            }
        }
    }
}
