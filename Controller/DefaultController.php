<?php

namespace Lyrixx\Bundle\LifestreamBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    /**
     * This is just a demo. Do not use it in prod.
     */
    public function indexAction(Request $request, $serviceName, $username)
    {
        $lifestream = $this
            ->get('lyrixx.lifestream.factory')
            ->createLifestream($serviceName, array($username))
        ;

        $response = $this->render('LyrixxLifestreamBundle:Default:index.html.twig', array(
            'lifestream' => $lifestream->boot(),
        ));
        $response->setMaxAge($this->container->getParameter('lyrixx.lifestream.cache.maxage'));

        return $response;
    }
}
