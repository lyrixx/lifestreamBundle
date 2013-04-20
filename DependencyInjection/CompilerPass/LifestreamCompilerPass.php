<?php

namespace Lyrixx\Bundle\LifestreamBundle\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Exception\OutOfBoundsException;

class LifestreamCompilerPass implements CompilerPassInterface
{
    private $availableServices = array();
    private $availableFilters = array();
    private $availableFormatters = array();

    private $defaultFormatters = array();
    private $defaultFilters = array();

    private $availableConcreatServices = array();

    public function process(ContainerBuilder $container)
    {
        $config = $container->getParameter('lyrixx.lifestream.config');
        $container->setParameter('lyrixx.lifestream.config', null);

        $this->availableServices = $this->extractSymfonyServicesAvailable($container, 'lyrixx.lifestream.service');
        $this->availableFormatters = $this->extractSymfonyServicesAvailable($container, 'lyrixx.lifestream.formatter');
        $this->availableFilters = $this->extractSymfonyServicesAvailable($container, 'lyrixx.lifestream.filter');

        $this->defaultFormatters = $this->validateFormatters($config['formatters'], '__default__');
        $this->defaultFilters = $this->validateFilters($config['filters'], '__default__');

        $services = array();
        $args = array();
        $formatters = array();
        $filters = array();

        foreach ($config['lifestream'] as $key => $config) {
            $services[$key] = $this->validateService($config['service'], $key);
            $formatters[$key] = $this->validateFormatters($config['formatters'], $key);
            $filters[$key] = $this->validateFilters($config['filters'], $key);
            $args[$key] = $config['args'];
        }

        foreach ($services as $key => $service) {
            if ('aggregate' === $service) {
                continue;
            }

            $serviceId = $this->availableServices[$service];
            $serviceDefinition = $container->getDefinition($serviceId);

            $nbArg = count($args[$key]);
            $nbArgMax = count($serviceDefinition->getArguments()) - 1;
            if ($nbArg > $nbArgMax) {
                throw new OutOfBoundsException(sprintf(
                    'The lifestream "%s" of type "%s" contains too much arguments (%d). It can contains at maximum %d argument(s).',
                    $key,
                    $service,
                    $nbArg,
                    $nbArgMax
                ));
            }

            $serviceDefinition = new DefinitionDecorator($serviceId);
            foreach ($args[$key] as $i => $arg) {
                $serviceDefinition->replaceArgument($i, $arg);
            }
            $container->setDefinition('lyrixx.lifestream.my_service.'.$key, $serviceDefinition);

            $lifestreamDefinition = new DefinitionDecorator('lyrixx.lifestream.lifestream');
            $lifestreamDefinition->replaceArgument(0, new Reference('lyrixx.lifestream.my_service.'.$key));

            $lifestreamDefinition->addMethodCall('setFilters', array($this->mergeDefaultFilters($filters[$key])));
            $lifestreamDefinition->addMethodCall('setFormatters', array($this->mergeDefaultFormatters($formatters[$key])));

            $container->setDefinition('lyrixx.lifestream.my.'.$key, $lifestreamDefinition);

            $this->availableConcreatServices[] = $key;
        }

        foreach ($services as $key => $service) {
            if ('aggregate' !== $service) {
                continue;
            }

            $argsTmp = array();
            foreach ($args[$key] as $arg) {
                if (!in_array($arg, $this->availableConcreatServices)) {
                    throw new \InvalidArgumentException(sprintf(
                        'The lifestream "%s" uses an unknow service "%s". Known services are "%s"',
                        $key,
                        $arg,
                        implode('", "', $this->availableConcreatServices)
                    ));
                }
                $argsTmp[] = new Reference('lyrixx.lifestream.my_service.'.$arg);
            }

            $serviceDefinition = new DefinitionDecorator($this->availableServices[$service]);
            $serviceDefinition->replaceArgument(0, $argsTmp);
            $container->setDefinition('lyrixx.lifestream.my_service.'.$key, $serviceDefinition);

            $lifestreamDefinition = new DefinitionDecorator('lyrixx.lifestream.lifestream');
            $lifestreamDefinition->replaceArgument(0, new Reference('lyrixx.lifestream.my_service.'.$key));

            $lifestreamDefinition->addMethodCall('setFilters', array($this->mergeDefaultFilters($filters[$key])));
            $lifestreamDefinition->addMethodCall('setFormatters', array($this->mergeDefaultFormatters($formatters[$key])));

            $container->setDefinition('lyrixx.lifestream.my.'.$key, $lifestreamDefinition);
        }

    }

    private function extractSymfonyServicesAvailable($container, $tag)
    {
        $services = array();

        foreach ($container->findTaggedServiceIds($tag) as $id => $tags) {
            foreach ($tags as $tag) {
                foreach ($tag as $attribute => $alias) {
                    if ('alias' == $attribute) {
                        $services[$alias] = $id;
                    }
                }
            }
        };

        return $services;
    }

    private function validateService($service, $key)
    {
        $this->validateSymfonyServices(array($service), $this->availableServices, $key, 'service');

        return $service;
    }

    private function validateFormatters($formatters, $key)
    {
        $this->validateSymfonyServices($formatters, $this->availableFormatters, $key, 'formatters');

        return $formatters;
    }

    private function validateFilters($filters, $key)
    {
        $this->validateSymfonyServices($filters, $this->availableFilters, $key, 'filters');

        return $filters;
    }

    private function validateSymfonyServices(array $symfonyServices, array $symfonyServicesAvailable, $key, $type)
    {
        foreach ($symfonyServices as $symfonyService) {
            if (!array_key_exists($symfonyService, $symfonyServicesAvailable)) {
                throw new \InvalidArgumentException(sprintf(
                    'The lifestream "%s" use an unexistant %s "%s". Available %ss: "%s".',
                    $key,
                    $type,
                    $symfonyService,
                    $type,
                    implode('", "', array_keys($symfonyServicesAvailable))
                ));
            }
        }
    }

    private function mergeDefaultFilters($filters)
    {
        return $this->mergeDefaults($filters, $this->defaultFilters, $this->availableFilters);
    }

    private function mergeDefaultFormatters($formatters)
    {
        return $this->mergeDefaults($formatters, $this->defaultFormatters, $this->availableFormatters);
    }

    private function mergeDefaults($symfonyServices, $defaultSymfonyServices, $symfonyServicesAvailable)
    {
        $symfonyServices = array_unique(array_merge($symfonyServices, $defaultSymfonyServices));

        $services = array();
        foreach ($symfonyServices as $symfonyService) {
            $services[] = new Reference($symfonyServicesAvailable[$symfonyService]);
        }

        return $services;
    }
}
