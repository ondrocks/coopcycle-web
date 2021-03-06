<?php

namespace AppBundle\EventListener;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AuthenticationWebSuccessHandler implements AuthenticationSuccessHandlerInterface
{

    public function __construct(UrlGeneratorInterface $router)
    {
        $this->router = $router;
    }

    public function onSecurityInteractiveLogin(InteractiveLoginEvent $event)
    {
        $token = $event->getAuthenticationToken();
        $request = $event->getRequest();
        $this->onAuthenticationSuccess($request, $token);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token)
    {
        if ($token->getUser()->hasRole('ROLE_ADMIN')) {
            return new RedirectResponse($this->router->generate('admin_index'));
        }

        if ($token->getUser()->hasRole('ROLE_RESTAURANT')) {
            return new RedirectResponse($this->router->generate('profile_orders'));
        }

        if ($token->getUser()->hasRole('ROLE_COURIER')) {
            return new RedirectResponse($this->router->generate('profile_tasks'));
        }

        if ($token->getUser()->hasRole('ROLE_STORE')) {
            return new RedirectResponse($this->router->generate('profile_stores'));
        }

        return new RedirectResponse($request->headers->get('referer'));
    }
}

