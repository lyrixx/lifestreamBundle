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

        $services = array();
        foreach ($container->findTaggedServiceIds('lyrixx.lifestream.service') as $id => $tags) {
            foreach ($tags as $tag) {
                foreach ($tag as $attribute => $alias) {
                    if ('alias' == $attribute) {
                        $services[$alias] = $id;
                    }
                }
            }
        };

        foreach ($config['lifestream'] as $key => $config) {
            $service = $config['service'];

            if (!array_key_exists($service, $services)) {
                throw new \InvalidArgumentException(sprintf(
                    'The lifestream "%s"of type "%s" is based on an unexistant type. Available services: "%s".',
                    $key,
                    $service,
                    implode('", "', array_keys($services))
                ));
            }

            $serviceId = $services[$service];
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
            $container->setDefinition('lyrixx.lifestream.my.'.$key, $lifestreamDefinition);
        }
    }
}
