<?php

declare(strict_types=1);

namespace App\Controller;

use Modufolio\Appkit\Core\AbstractController;
use Modufolio\Appkit\Security\Csrf\CsrfTokenManagerInterface;
use Modufolio\Appkit\Template\Template;
use Modufolio\Psr7\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route(path: '/', name: 'home', methods: ['GET'])]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $template = new Template(
            name: 'home',
            templatePaths: [dirname(__DIR__, 2).'/resources/views'],
            layoutPaths: [dirname(__DIR__, 2).'/resources/views/layouts'],
            request: $request,
        );

        return new Response(body: $template->render([
            'title' => 'Welcome',
            'user' => $this->getUser(),
            'logout_csrf' => $this->csrfTokenManager->getToken('logout')->getValue(),
        ]));
    }
}
