<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;
use Livewire\Livewire;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);
    Features::passkeys([
        'confirmPassword' => true,
    ]);
});

test('security settings page can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'));

    $response->assertOk();

    $response->assertSee('Passkeys');
    $response->assertSee('No passkeys yet');
    $response->assertSee('Two-factor authentication');
    $response->assertSee('Enable 2FA');
    $response->assertSeeLivewire('pages::settings.two-factor-setup-modal');
});

test('enabled two factor authentication shows recovery codes from the settings component', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertSeeLivewire('pages::settings.recovery-codes')
        ->assertSee('2FA recovery codes')
        ->assertSee('Disable 2FA');
});

test('two factor setup keeps its verification and reset steps together', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.two-factor-setup-modal', ['requiresConfirmation' => true])
        ->call('startTwoFactorSetup')
        ->assertHasNoErrors()
        ->assertSet('manualSetupKey', fn (string $key): bool => $key !== '')
        ->call('showVerificationIfNecessary')
        ->assertSet('showVerificationStep', true)
        ->assertSee('Verify authentication code')
        ->set('code', '123')
        ->call('confirmTwoFactor')
        ->assertHasErrors(['code' => 'size'])
        ->call('resetVerification')
        ->assertSet('showVerificationStep', false)
        ->assertSet('code', '')
        ->call('closeModal')
        ->assertSet('manualSetupKey', '')
        ->assertSet('qrCodeSvg', '');

    expect($user->fresh()->two_factor_confirmed_at)->toBeNull();
});

test('appearance settings still provide light dark and system themes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('appearance.edit'))
        ->assertSee('Appearance settings')
        ->assertSee('Light')
        ->assertSee('Dark')
        ->assertSee('System');
});

test('security settings page requires password confirmation when enabled', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('security.edit'));

    $response->assertRedirect(route('password.confirm'));
});

test('security settings page renders without two factor when feature is disabled', function () {
    config(['fortify.features' => []]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertSee('Update password')
        ->assertDontSee('Manage your passkeys for passwordless sign-in')
        ->assertDontSee('Add a passkey to sign in without a password')
        ->assertDontSee('Two-factor authentication');
});

test('two factor authentication disabled when confirmation abandoned between requests', function () {
    $user = User::factory()->create();

    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => null,
    ])->save();

    $this->actingAs($user);

    $component = Livewire::test('pages::settings.security');

    $component->assertSet('twoFactorEnabled', false);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
    ]);
});

test('password can be updated', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.security')
        ->set('current_password', 'password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword');

    $response->assertHasNoErrors();

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.security')
        ->set('current_password', 'wrong-password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword');

    $response->assertHasErrors(['current_password'])
        ->assertSet('current_password', '')
        ->assertSet('password', '')
        ->assertSet('password_confirmation', '');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});
