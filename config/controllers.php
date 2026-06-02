<?php

declare(strict_types=1);

use App\Controller\HomeController;
use App\Controller\SecurityController;
use Modufolio\Appkit\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

return [
    HomeController::class => [
        CsrfTokenManagerInterface::class,
    ],
    SecurityController::class => [
        CsrfTokenManagerInterface::class,
        SessionInterface::class,
    ],
];
