<?php

declare(strict_types=1);

namespace App\Controller;

use Modufolio\Appkit\Core\AbstractController;
use Modufolio\Appkit\Security\Csrf\CsrfTokenManagerInterface;
use Modufolio\Appkit\Template\Template;
use Modufolio\Psr7\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Attribute\Route;

class SecurityController extends AbstractController
{
    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly FlashBagAwareSessionInterface $session,
    ) {
    }

    #[Route(path: '/login', name: 'login', methods: ['GET', 'POST'])]
    public function login(ServerRequestInterface $request): ResponseInterface
    {
        // On a successful login POST the firewall has already authenticated the
        // user before this controller runs, so getUser() is set here. The
        // firewall itself attaches the remember-me Set-Cookie header (when
        // requested) before this method runs, so we just redirect.
        if (null !== $this->getUser()) {
            return Response::redirect($this->urlGenerator->generate('home'));
        }

        $error = $this->session->getFlashBag()->get('error')[0] ?? null;

        $template = new Template(
            name: 'login',
            templatePaths: [dirname(__DIR__, 2).'/resources/views'],
            layoutPaths: [dirname(__DIR__, 2).'/resources/views/layouts'],
            request: $request,
        );

        return new Response(body: $template->render([
            'title' => 'Sign in',
            'error' => $error,
            'csrf_token' => $this->csrfTokenManager->getToken('authenticate')->getValue(),
        ]));
    }

    #[Route(path: '/logout', name: 'logout', methods: ['POST'])]
    public function logout(): never
    {
        // Intercepted by the firewall logout listener before this method runs.
        // The route exists only so the URL generator can resolve `logout`.
        throw new \LogicException('This should never be reached.');
    }
}
