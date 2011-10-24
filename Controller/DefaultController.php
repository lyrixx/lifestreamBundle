<?php

namespace Lx\LifestreamBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction($serviceName, $username)
    {
        $service = $this->get('lx.lifestream.'.$serviceName);
        $service->setUsername($username);

        $maxItemDefault = $this->container->getParameter('lx.lifestream.stream.max_items');
        $maxItem = (int) $this->getRequest()->get('maxItem', $maxItemDefault);
        $stream = $service->processFeed()->getStream($maxItem);

        $response = $this->render('LxLifestreamBundle:Default:index.html.twig', array(
            'service'       => $service,
            'serviceName'   => $serviceName,
            'username'      => $username,
            'stream'        => $stream,
        ));
        $response->setMaxAge($this->container->getParameter('lx.lifestream.cache.maxage'));

        return $response;
    }
}
