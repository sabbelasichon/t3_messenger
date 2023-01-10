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
                    'failure_transport' => null,
                    'options' => [],
                    'serializer' => null,
                ]);

            $transportResolver->setDefault('retry_strategy', function (OptionsResolver $retryStrategyResolver) {
                $retryStrategyResolver->setDefaults([
                    'service' => null,
                    'max_retries' => 3,
                    'delay' => 1000,
                    'multiplier' => 2,
                    'max_delay' => 0,
                ]);

                $retryStrategyResolver->setAllowedTypes('max_retries', 'integer');
                $retryStrategyResolver->setAllowedTypes('delay', 'integer');
                $retryStrategyResolver->setAllowedTypes('multiplier', ['float', 'integer']);
                $retryStrategyResolver->setAllowedTypes('max_delay', 'integer');
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
}
