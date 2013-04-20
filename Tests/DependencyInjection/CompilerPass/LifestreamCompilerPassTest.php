<?php

namespace Lyrixx\Bundle\LifestreamBundle\Tests\DependencyInjection\CompilerPass;

use Lyrixx\Bundle\LifestreamBundle\DependencyInjection\CompilerPass\LifestreamCompilerPass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class LifestreamCompilerPassTest extends \PHPUnit_Framework_TestCase
{
    private $container;
    private $compiler;

    public function setUp()
    {
        $this->container = new ContainerBuilder();
        $loader = new XmlFileLoader($this->container, new FileLocator(__DIR__.'/../../../Resources/config'));
        $loader->load('services.xml');

        $this->compiler = new LifestreamCompilerPass();
    }

    public function testProcessWithoutConfig()
    {
        $this->compiler->process($this->container);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The lifestream "my" use an unexistant service "service_which_doest_not_exists". Available services: "twitter", "twitter_search", "twitter_list", "github", "rss20", "atom", "aggregate".
     */
    public function testProcessValidateService()
    {
        $this->container->setParameter('lyrixx.lifestream.config', array(
            'formatters' => array(),
            'filters' => array(),
            'lifestream' => array(
                'my' => array(
                    'service' => 'service_which_doest_not_exists',
                    'args' => array(),
                    'formatters' => array(),
                    'filters' => array(),
                ),
            ),
        ));

        $this->compiler->process($this->container);
    }

    /**
     * @expectedException Symfony\Component\DependencyInjection\Exception\OutOfBoundsException
     * @expectedExceptionMessage The lifestream "my" of type "atom" contains too much arguments (4). It can contains at maximum 3 argument(s).
     */
    public function testProcessValidateArgs()
    {
        $this->container->setParameter('lyrixx.lifestream.config', array(
            'formatters' => array(),
            'filters' => array(),
            'lifestream' => array(
                'my' => array(
                    'service' => 'atom',
                    'args' => array(1, 2, 3, 4),
                    'formatters' => array(),
                    'filters' => array(),
                ),
            ),
        ));

        $this->compiler->process($this->container);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The lifestream "my" use an unexistant formatter "foo". Available formatters: "link", "twitter_hashtag", "twitter_mention".
     */
    public function testProcessValidateFormatters()
    {
        $this->container->setParameter('lyrixx.lifestream.config', array(
            'formatters' => array(),
            'filters' => array(),
            'lifestream' => array(
                'my' => array(
                    'service' => 'atom',
                    'args' => array(),
                    'formatters' => array('foo'),
                    'filters' => array(),
                ),
            ),
        ));

        $this->compiler->process($this->container);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The lifestream "my" use an unexistant filter "foo". Available filters: "twitter_mention", "twitter_retweet".
     */
    public function testProcessValidateFilters()
    {
        $this->container->setParameter('lyrixx.lifestream.config', array(
            'formatters' => array(),
            'filters' => array(),
            'lifestream' => array(
                'my' => array(
                    'service' => 'atom',
                    'args' => array(),
                    'formatters' => array(),
                    'filters' => array('foo'),
                ),
            ),
        ));

        $this->compiler->process($this->container);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The lifestream "__default__" use an unexistant formatter "foo". Available formatters: "link", "twitter_hashtag", "twitter_mention".
     */
    public function testProcessValidateGlobalFormatters()
    {
        $this->container->setParameter('lyrixx.lifestream.config', array(
            'formatters' => array('foo'),
            'filters' => array(),
            'lifestream' => array(),
        ));

        $this->compiler->process($this->container);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The lifestream "__default__" use an unexistant filter "foo". Available filters: "twitter_mention", "twitter_retweet".
     */
    public function testProcessValidateGlobalFilters()
    {
        $this->container->setParameter('lyrixx.lifestream.config', array(
            'formatters' => array(),
            'filters' => array('foo'),
            'lifestream' => array(),
        ));

        $this->compiler->process($this->container);
    }

    public function testProcessCreateLifestream()
    {
        $this->container->setParameter('lyrixx.lifestream.config', array(
            'formatters' => array(),
            'filters' => array(),
            'lifestream' => array(
                'twitter_lyrixx' => array(
                    'service' => 'twitter',
                    'args' => array('lyrixx'),
                    'formatters' => array(),
                    'filters' => array(),
                ),
            ),
        ));

        $this->compiler->process($this->container);
        $this->container->compile();

        $this->assertTrue($this->container->has('lyrixx.lifestream.my.twitter_lyrixx'));
        $lifestream = $this->container->get('lyrixx.lifestream.my.twitter_lyrixx');
        $this->assertInstanceOf('Lyrixx\Lifestream\Lifestream', $lifestream);
        $this->assertInstanceOf('Lyrixx\Lifestream\Service\Twitter', $lifestream->getService());
        $this->assertSame('https://twitter.com/lyrixx', $lifestream->getService()->getProfileUrl());
    }

    public function testProcessCreateLifestreamWithFiltersAndFormatters()
    {
        $this->container->setParameter('lyrixx.lifestream.config', array(
            'formatters' => array('link'),
            'filters' => array('twitter_retweet'),
            'lifestream' => array(
                'twitter_lyrixx' => array(
                    'service' => 'twitter',
                    'args' => array('lyrixx'),
                    'formatters' => array('twitter_mention', 'twitter_hashtag'),
                    'filters' => array('twitter_mention'),
                ),
            ),
        ));

        $this->compiler->process($this->container);
        $this->container->compile();

        $lifestream = $this->container->get('lyrixx.lifestream.my.twitter_lyrixx');

        $lifestreamReflected = new \ReflectionObject($lifestream);
        $formatters = $lifestreamReflected->getProperty('formatters');
        $formatters->setAccessible(true);
        $formatters = $formatters->getValue($lifestream);
        $this->assertCount(3, $formatters);

        $filters = $lifestreamReflected->getProperty('filters');
        $filters->setAccessible(true);
        $filters = $filters->getValue($lifestream);
        $this->assertCount(2, $filters);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The lifestream "twitter_lyrixx" uses an unknow service "twitter_lyrixx2". Known services are ""
     */
    public function testProcessValidateAggregate()
    {
        $this->container->setParameter('lyrixx.lifestream.config', array(
            'formatters' => array(),
            'filters' => array(),
            'lifestream' => array(
                'twitter_lyrixx' => array(
                    'service' => 'aggregate',
                    'args' => array('twitter_lyrixx2'),
                    'formatters' => array(),
                    'filters' => array(),
                ),
            ),
        ));

        $this->compiler->process($this->container);
    }

    public function testProcessAggregate()
    {
        $this->container->setParameter('lyrixx.lifestream.config', array(
            'formatters' => array(),
            'filters' => array(),
            'lifestream' => array(
                'twitter_lyrixx' => array(
                    'service' => 'aggregate',
                    'args' => array('twitter_lyrixx2'),
                    'formatters' => array(),
                    'filters' => array(),
                ),
                'twitter_lyrixx2' => array(
                    'service' => 'twitter',
                    'args' => array('lyrixx'),
                    'formatters' => array(),
                    'filters' => array(),
                ),
            ),
        ));

        $this->compiler->process($this->container);
        $this->container->compile();

        $this->assertTrue($this->container->has('lyrixx.lifestream.my.twitter_lyrixx'));
        $lifestream = $this->container->get('lyrixx.lifestream.my.twitter_lyrixx');
        $this->assertInstanceOf('Lyrixx\Lifestream\Lifestream', $lifestream);
        $service = $lifestream->getService();
        $this->assertInstanceOf('Lyrixx\Lifestream\Service\Aggregate', $service);

        $serviceReflected = new \ReflectionObject($service);
        $services = $serviceReflected->getProperty('services');
        $services->setAccessible(true);
        $services = $services->getValue($service);
        $this->assertCount(1, $services);
    }

    public function tearDown()
    {
        $this->compiler = null;
    }
}
