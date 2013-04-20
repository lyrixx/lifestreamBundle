<?php

namespace Lyrixx\Bundle\LifestreamBundle\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Exception\OutOfBoundsException;

class LifestreamCompilerPass implements CompilerPassInterface
{
    const SERVICE_INTERFACE = 'Lyrixx\Lifestream\Service\ServiceInterface';

    private $availableServices = array();
    private $availableFilters = array();
    private $availableFormatters = array();

    private $defaultFormatters = array();
    private $defaultFilters = array();

    private $availableConcreatServices = array();

    public function process(ContainerBuilder $container)
    {
        if (!$container->hasParameter('lyrixx.lifestream.config')) {
            return;
        }

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

            $class = $container->getParameterBag()->resolveValue($container->getDefinition($serviceId)->getClass());
            $r = new \ReflectionClass($class);
            if (!$r->implementsInterface(static::SERVICE_INTERFACE)) {
                throw new \InvalidArgumentException(sprintf(
                    'The service "%s" (class: "%s"; used by the lifestream "%s") does not implements "%s"',
                    $service,
                    $class,
                    $key,
                    static::SERVICE_INTERFACE
                ));
            }

            $r = new \ReflectionMethod($class, '__construct');
            $min = 0;
            $max = 0;
            foreach ($r->getParameters() as $param) {
                $max++;
                if (!$param->isDefaultValueAvailable()) {
                    $min++;
                }
            }
            $nbArg = count($args[$key]);
            if ($nbArg > $max) {
                throw new OutOfBoundsException(sprintf(
                    'The lifestream "%s" of type "%s" contains too much arguments (%d). It should contains at maximum %d argument(s).',
                    $key,
                    $service,
                    $nbArg,
                    $max
                ));
            } elseif ($nbArg < $min) {
                throw new OutOfBoundsException(sprintf(
                    'The lifestream "%s" of type "%s" contains too few arguments (%d). It should contains at least %d argument(s).',
                    $key,
                    $service,
                    $nbArg,
                    $min
                ));
            }

            $serviceDefinition = new DefinitionDecorator($serviceId);
            $serviceDefinition->setArguments($args[$key]);
            $serviceDefinition->addMethodCall('setClient', array(new Reference('lyrixx.lifestream.client')));
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
            $serviceDefinition->addArgument($argsTmp);
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
        $this->validateSymfonyServices($formatters, $this->availableFormatters, $key, 'formatter');

        return $formatters;
    }

    private function validateFilters($filters, $key)
    {
        $this->validateSymfonyServices($filters, $this->availableFilters, $key, 'filter');

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
