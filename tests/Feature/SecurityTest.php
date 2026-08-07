<?php

declare(strict_types=1);

namespace App\Tests\Feature;

use App\Entity\User;
use App\Tests\Case\AppTestCase;
use Modufolio\Appkit\Security\User\UserPasswordHasherInterface;

/**
 * Drives the login flow through the firewall with real requests, rather than
 * asserting on the authenticator in isolation: the parts worth protecting here
 * are the ones that only exist once the firewall, the CSRF gate and the session
 * are wired together.
 */
final class SecurityTest extends AppTestCase
{
    protected function setUp(): void
    {
        $this->refreshDatabase();

        $em = $this->app()->entityManager();
        $hasher = $this->app()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail('johndoe@example.com');
        $user->setPassword($hasher->hashPassword($user, 'secret'));

        $em->persist($user);
        $em->flush();
    }

    /**
     * The firewall covers '/', so the entry point has to stay reachable — if it
     * did not, an anonymous visitor would be redirected to a page they cannot
     * load either.
     */
    public function testLoginPageIsReachableAnonymously(): void
    {
        $this->get('/login')->assertStatus(200);
    }

    public function testLoginPageRendersACsrfToken(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $this->assertStringContainsString(
            $this->app()->csrfTokenManager()->getToken('authenticate')->getValue(),
            $response->getContent(),
        );
    }

    public function testValidCredentialsAuthenticateAndRedirectHome(): void
    {
        $response = $this->form('/login', [
            'email' => 'johndoe@example.com',
            'password' => 'secret',
            '_csrf_token' => $this->app()->csrfTokenManager()->getToken('authenticate')->getValue(),
        ]);

        $response->assertRedirect('/');
        $this->assertNotNull($this->app()->tokenStorage()->getToken());
    }

    public function testAWrongPasswordLeavesTheVisitorAnonymous(): void
    {
        $this->form('/login', [
            'email' => 'johndoe@example.com',
            'password' => 'not-the-password',
            '_csrf_token' => $this->app()->csrfTokenManager()->getToken('authenticate')->getValue(),
        ]);

        $this->assertNull(
            $this->app()->tokenStorage()->getToken(),
            'A failed login must not leave an authentication token behind.',
        );

        // And the protected page still turns them away.
        $this->get('/')->assertRedirect('/login');
    }

    public function testAnUnknownEmailIsRejectedTheSameWay(): void
    {
        $this->form('/login', [
            'email' => 'nobody@example.com',
            'password' => 'secret',
            '_csrf_token' => $this->app()->csrfTokenManager()->getToken('authenticate')->getValue(),
        ]);

        $this->assertNull($this->app()->tokenStorage()->getToken());
    }

    /**
     * Without this the login POST is forgeable from another origin, so it is
     * worth pinning rather than trusting the firewall default to stay put.
     */
    public function testALoginPostWithoutACsrfTokenIsRejected(): void
    {
        $this->form('/login', [
            'email' => 'johndoe@example.com',
            'password' => 'secret',
        ]);

        $this->assertNull($this->app()->tokenStorage()->getToken());
    }

    public function testLogoutEndsTheSession(): void
    {
        $this->login();
        $this->assertNotNull($this->app()->tokenStorage()->getToken());

        $this->logout();

        $this->assertNull($this->app()->tokenStorage()->getToken());
        $this->get('/')->assertRedirect('/login');
    }
}
