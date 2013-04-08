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

        foreach ($config['lifestream'] as $key => $config) {
            try {
                $definitionOriginal = $container->getDefinition('lyrixx.lifestream.service.'.$config['service']);
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException(sprintf(
                    'The lifestream "%s"of type "%s" is based on an unexistant type',
                    $key,
                    $config['service']
                ));
            }

            $nbArg = count($config['args']);
            $nbArgMax = count($definitionOriginal->getArguments()) - 1;
            if ($nbArg > $nbArgMax) {
                throw new OutOfBoundsException(sprintf(
                    'The lifestream "%s" of type "%s" contains too much arguments (%d). It can contains at maximum %d.',
                    $key,
                    $config['service'],
                    $nbArg,
                    $nbArgMax
                ));
            }

            $serviceDefinition = new DefinitionDecorator('lyrixx.lifestream.service.'.$config['service']);
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
