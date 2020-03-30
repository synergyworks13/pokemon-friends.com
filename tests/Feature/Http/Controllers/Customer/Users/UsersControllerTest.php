<?php

namespace Tests\Feature\Http\Controllers\Customer\Users;

use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\One\User as SocialiteOAuthOneUser;
use template\Domain\Users\ProvidersTokens\ProviderToken;
use template\Domain\Users\Users\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class UsersControllerTest extends TestCase
{
    use DatabaseMigrations;

    public function testToVisitDashboardAsAnonymous()
    {
        $this
            ->get('/users/dashboard')
            ->assertStatus(302)
            ->assertRedirect('/login');
    }

    public function testToVisitDashboardAsCustomer()
    {
        $this->actingAsCustomer();
        $this
            ->assertAuthenticated()
            ->get('/users/dashboard')
            ->assertStatus(200)
            ->assertSeeText('Dashboard');
    }

    public function testToVisitAnonymousDashboardAsCustomer()
    {
        $this->actingAsCustomer();
        $this
            ->assertAuthenticated()
            ->get('/')
            ->assertStatus(302)
            ->assertRedirect('/users/dashboard');
    }

    public function testToSubmitUpdatePassword()
    {
        $newPassword = $this->faker->password(8);
        $user = $this->actingAsCustomer();
        $this
            ->assertAuthenticated()
            ->from("/users/{$user->uniqid}/edit")
            ->put("/users/password/{$user->uniqid}", [
                'password_current' => $this->getDefaultPassword(),
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ])
            ->assertRedirect("/users/{$user->uniqid}/edit");
        $user->refresh();
        $this->assertFalse(Hash::check($this->getDefaultPassword(), $user->password));
        $this->assertTrue(Hash::check($newPassword, $user->password));
    }

    public function testToSubmitUpdatePasswordWithEmptyForm()
    {
        $user = $this->actingAsCustomer();
        $this
            ->assertAuthenticated()
            ->followingRedirects()
            ->from("/users/{$user->uniqid}/edit")
            ->put("/users/password/{$user->uniqid}", [
                'password_current' => null,
                'password' => null,
                'password_confirmation' => null,
            ])
            ->assertSuccessful()
            ->assertSeeText('The current password field is required.')
            ->assertSeeText('The password field is required.');
        $user->refresh();
        $this->assertNotNull($user->password);
        $this->assertFalse(Hash::check(null, $user->password));
    }

    public function testToSubmitUpdatePasswordWithInvalidCurrentPassword()
    {
        $newPassword = $this->faker->password(8);
        $user = $this->actingAsCustomer();
        $this
            ->assertAuthenticated()
            ->followingRedirects()
            ->from("/users/{$user->uniqid}/edit")
            ->put("/users/password/{$user->uniqid}", [
                'password_current' => $this->faker->word,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ])
            ->assertSuccessful()
            ->assertSeeText('The password entered is not your password.');
        $user->refresh();
        $this->assertNotNull($user->password);
        $this->assertFalse(Hash::check(null, $user->password));
    }

    public function testToSubmitUpdatePasswordWithPasswordMismatch()
    {
        $newPassword = $this->faker->password(8);
        $user = $this->actingAsCustomer();
        $this
            ->assertAuthenticated()
            ->followingRedirects()
            ->from("/users/{$user->uniqid}/edit")
            ->put("/users/password/{$user->uniqid}", [
                'password_current' => $this->getDefaultPassword(),
                'password' => $newPassword,
                'password_confirmation' => $this->faker->password(10),
            ])
            ->assertSuccessful()
            ->assertSeeText('The password confirmation does not match.');
        $user->refresh();
        $this->assertNotNull($user->password);
        $this->assertFalse(Hash::check(null, $user->password));
    }

    public function testToSubmitUpdatePasswordWithPasswordTooShort()
    {
        $newPassword = $this->faker->password(3, 7);
        $user = $this->actingAsCustomer();
        $this
            ->assertAuthenticated()
            ->followingRedirects()
            ->from("/users/{$user->uniqid}/edit")
            ->put("/users/password/{$user->uniqid}", [
                'password_current' => $this->getDefaultPassword(),
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ])
            ->assertSuccessful()
            ->assertSeeText('The password must be at least 8 characters.');
        $user->refresh();
        $this->assertFalse(Hash::check($newPassword, $user->password));
        $this->assertTrue(Hash::check($this->getDefaultPassword(), $user->password));
    }

    public function testToSubmitUpdateProfile()
    {
        $user = $this->actingAsCustomer();
        $this
            ->assertAuthenticated()
            ->from("/users/{$user->uniqid}/edit")
            ->put("/users/{$user->uniqid}", [
                'friend_code' => $user->profile->friend_code,
                'team_color' => $user->profile->team_color,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'civility' => $user->civility,
                'timezone' => $user->timezone,
                'locale' => $user->locale,
            ])
            ->assertRedirect("/users/{$user->uniqid}/edit");
    }

    public function testToLinkAccountOnSocialProviderUser()
    {
        $user = $this->actingAsCustomer();
        $abstractUser = \Mockery::mock(SocialiteOAuthOneUser::class);
        $abstractUser->token = $this->faker->uuid;
        $abstractUser->id = $this->faker->uuid;
        $abstractUser
            ->shouldReceive('getId')
            ->andReturn($abstractUser->id)
            ->shouldReceive('getEmail')
            ->andReturn($this->faker->email)
            ->shouldReceive('getNickname')
            ->andReturn($this->faker->userName)
            ->shouldReceive('getName')
            ->andReturn($this->faker->name)
            ->shouldReceive('getAvatar')
            ->andReturn('https://en.gravatar.com/userimage');

        $provider = \Mockery::mock(SocialiteProvider::class);
        $provider
            ->shouldReceive('user')
            ->andReturn($abstractUser);

        Socialite::shouldReceive('driver')
            ->with(ProviderToken::TWITTER)
            ->andReturn($provider);

        $this
            ->from("/users/{$user->uniqid}/edit")
            ->get("/login/twitter/callback")
            ->assertRedirect("/users/{$user->uniqid}/edit")
            ->assertSessionHas(
                'message-success',
                'The link between your twitter account and your user account is correctly completed'
            );

        $this->assertDatabaseHas('users_providers_tokens', [
            'user_id' => $user->id,
            'provider' => ProviderToken::TWITTER,
            'provider_id' => $abstractUser->id,
            'provider_token' => $abstractUser->token,
        ]);
    }

    public function testToLinkAccountOnSocialProviderUserWithAlreadyLinkedAccount()
    {
        $user = factory(User::class)
            ->states(User::ROLE_CUSTOMER)
            ->create();
        $provider_token = factory(ProviderToken::class)
            ->states(ProviderToken::TWITTER)
            ->create(['user_id' => $user->id]);
        $user = $this->actingAsCustomer();
        $abstractUser = \Mockery::mock(SocialiteOAuthOneUser::class);
        $abstractUser->token = $provider_token->provider_token;
        $abstractUser->id = $provider_token->provider_id;
        $abstractUser
            ->shouldReceive('getId')
            ->andReturn($abstractUser->id)
            ->shouldReceive('getEmail')
            ->andReturn($this->faker->email)
            ->shouldReceive('getNickname')
            ->andReturn($this->faker->userName)
            ->shouldReceive('getName')
            ->andReturn($this->faker->name)
            ->shouldReceive('getAvatar')
            ->andReturn('https://en.gravatar.com/userimage');

        $provider = \Mockery::mock(SocialiteProvider::class);
        $provider
            ->shouldReceive('user')
            ->andReturn($abstractUser);

        Socialite::shouldReceive('driver')
            ->with(ProviderToken::TWITTER)
            ->andReturn($provider);

        $this
            ->from("/users/{$user->uniqid}/edit")
            ->get("/login/twitter/callback")
            ->assertRedirect("/users/{$user->uniqid}/edit")
            ->assertSessionHas(
                'message-error',
                'The link of your twitter account with your user account could not be done'
            );

        $this->assertDatabaseMissing('users_providers_tokens', [
            'user_id' => $user->id,
            'provider' => ProviderToken::TWITTER,
            'provider_id' => $abstractUser->id,
            'provider_token' => $abstractUser->token,
        ]);
    }
}
