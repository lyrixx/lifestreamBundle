<?php

namespace Lyrixx\Bundle\LifestreamBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Lyrixx\Bundle\LifestreamBundle\DependencyInjection\CompilerPass\LifestreamCompilerPass;

class LyrixxLifestreamBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new LifestreamCompilerPass());
    }
}
