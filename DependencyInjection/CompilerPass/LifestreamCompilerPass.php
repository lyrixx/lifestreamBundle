<?php

namespace Lyrixx\Bundle\LifestreamBundle\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Exception\OutOfBoundsException;

class LifestreamCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $config = $container->getParameter('lyrixx.lifestream.config');

        $servicesAvailable = $this->extractSymfonyServicesAvailable($container, 'lyrixx.lifestream.service');
        $formattersAvailable = $this->extractSymfonyServicesAvailable($container, 'lyrixx.lifestream.formatter');
        $filtersAvailable = $this->extractSymfonyServicesAvailable($container, 'lyrixx.lifestream.filter');

        foreach ($config['lifestream'] as $key => $config) {
            $this->validateSymfonyServices($service = $config['service'], $servicesAvailable, $key, 'service');
            $formatters = $this->validateSymfonyServices($config['formatters'], $formattersAvailable, $key, 'formatter');
            $filters = $this->validateSymfonyServices($config['filters'], $filtersAvailable, $key, 'filter');

            $serviceId = $servicesAvailable[$service];
            $serviceDefinition = $container->getDefinition($serviceId);

            $nbArg = count($config['args']);
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
            foreach ($config['args'] as $k => $arg) {
                $serviceDefinition->replaceArgument($k, $arg);
            }
            $container->setDefinition('lyrixx.lifestream.my_service.'.$key, $serviceDefinition);

            $lifestreamDefinition = new DefinitionDecorator('lyrixx.lifestream.lifestream');
            $lifestreamDefinition->replaceArgument(0, new Reference('lyrixx.lifestream.my_service.'.$key));

            foreach ($filters as $k => $filter) {
                $filters[$k] = new Reference($filtersAvailable[$filter]);
            }
            $lifestreamDefinition->addMethodCall('setFilters', array($filters));

            foreach ($formatters as $k => $formatter) {
                $formatters[$k] = new Reference($formattersAvailable[$formatter]);
            }
            $lifestreamDefinition->addMethodCall('setFormatters', array($formatters));

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

    private function validateSymfonyServices($symfonyServices, $symfonyServicesAvailable, $key, $type)
    {
        if (!is_array($symfonyServices)) {
            $symfonyServices = array($symfonyServices);
        }

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

        return $symfonyServices;
    }
}
