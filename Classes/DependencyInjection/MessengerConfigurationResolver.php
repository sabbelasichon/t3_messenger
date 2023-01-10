<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_tactician" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\DependencyInjection;

use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
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

        $resolver->define('failure_transport')
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
                    'default_middleware' => true,
                    'middleware' => [],
                ],
            ]);

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
                ->define('failure_transport')
                ->default(null)
                ->info('Transport name to send failed messages to (after all retries have failed).');

            $transportResolver->setDefault('retry_strategy', function (OptionsResolver $retryStrategyResolver) {
                $retryStrategyResolver->setDefaults([
                    'service' => null,
                    'max_retries' => 3,
                ]);

                $retryStrategyResolver
                    ->define('delay')
                    ->default(1000)
                    ->allowedTypes('integer')
                    ->info('Time in ms to delay (or the initial value when multiplier is used)');

                $retryStrategyResolver
                    ->define('multiplier')
                    ->default(2)
                    ->allowedTypes('float', 'integer')
                    // TODO: Minimum 1 validation
                    ->info(
                        'If greater than 1, delay will grow exponentially for each retry: this delay = (delay * (multiple ^ retries))'
                    );

                $retryStrategyResolver
                    ->define('max_delay')
                    ->default(0)
                    ->allowedTypes('integer')
                    // TODO: Minimum 0 validation
                    ->info('Max time in ms that a retry should ever be delayed (0 = infinite)');

                $retryStrategyResolver->setAllowedTypes('max_retries', 'integer');
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
