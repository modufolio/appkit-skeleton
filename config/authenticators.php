<?php

declare(strict_types=1);

use App\Repository\UserRepository;
use Modufolio\Appkit\Security\Authenticator\FormLoginAuthenticator;
use Modufolio\Appkit\Security\Csrf\CsrfTokenManagerInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

return [
    'form_login' => function (ContainerInterface $container) {
        return new FormLoginAuthenticator(
            $container->get(UserRepository::class),
            $container->get(CsrfTokenManagerInterface::class),
            $container->get(SessionInterface::class),
            null,
            options: [
                'check_path' => '/login',
                'login_path' => '/login',
            ],
        );
    },
];
