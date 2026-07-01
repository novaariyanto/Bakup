<?php

use App\Enums\SftpAuthenticationMethod;
use App\Services\Storage\Sftp\SftpAuthenticationResolver;
use App\Services\Storage\Sftp\SftpConfigurationValidator;
use App\Services\Storage\Sftp\SftpErrorMapper;
use League\Flysystem\PhpseclibV3\UnableToAuthenticate;
use League\Flysystem\PhpseclibV3\UnableToConnectToSftpHost;
use League\Flysystem\PhpseclibV3\UnableToLoadPrivateKey;

it('builds password-only flysystem config without private key', function () {
    $resolver = app(SftpAuthenticationResolver::class);

    $config = $resolver->buildFlysystemConfig([
        'host' => '10.0.0.1',
        'port' => 22,
        'username' => 'backup',
        'auth_method' => 'password',
        'password' => 'secret',
        'private_key' => '-----BEGIN OPENSSH PRIVATE KEY-----\nignored',
        'root' => '/backups',
    ]);

    expect($config)->toHaveKeys(['host', 'port', 'username', 'password', 'root']);
    expect($config)->not->toHaveKey('privateKey');
    expect($config)->not->toHaveKey('passphrase');
});

it('builds private key flysystem config without password', function () {
    $resolver = app(SftpAuthenticationResolver::class);

    $config = $resolver->buildFlysystemConfig([
        'host' => '10.0.0.1',
        'port' => 22,
        'username' => 'backup',
        'auth_method' => 'private_key',
        'password' => 'should-not-be-used',
        'private_key' => "-----BEGIN OPENSSH PRIVATE KEY-----\nabc\n-----END OPENSSH PRIVATE KEY-----",
        'passphrase' => 'phrase',
        'root' => '/',
    ]);

    expect($config)->toHaveKey('privateKey');
    expect($config)->not->toHaveKey('password');
    expect($config['passphrase'])->toBe('phrase');
});

it('merges sftp config without carrying private key for password auth', function () {
    $resolver = app(SftpAuthenticationResolver::class);

    $merged = $resolver->mergeConfig(
        [
            'host' => '10.0.0.1',
            'port' => 22,
            'username' => 'backup',
            'auth_method' => 'private_key',
            'private_key' => 'old-key',
            'root' => '/',
        ],
        [
            'auth_method' => 'password',
            'password' => 'new-secret',
        ],
    );

    expect($merged['auth_method'])->toBe('password');
    expect($merged)->toHaveKey('password');
    expect($merged)->not->toHaveKey('private_key');
});

it('rejects private key when password auth is selected', function () {
    $validator = app(SftpConfigurationValidator::class);

    expect(fn () => $validator->validate([
        'host' => '10.0.0.1',
        'username' => 'backup',
        'auth_method' => 'password',
        'password' => 'secret',
        'private_key' => '-----BEGIN OPENSSH PRIVATE KEY-----',
    ]))->toThrow(App\Exceptions\StorageDestinationException::class);
});

it('validates supported private key formats', function () {
    $validator = app(SftpConfigurationValidator::class);

    $validator->validatePrivateKeyFormat("-----BEGIN RSA PRIVATE KEY-----\nabc\n-----END RSA PRIVATE KEY-----");
    $validator->validatePrivateKeyFormat("-----BEGIN OPENSSH PRIVATE KEY-----\nabc\n-----END OPENSSH PRIVATE KEY-----");

    expect(fn () => $validator->validatePrivateKeyFormat('not-a-key'))
        ->toThrow(App\Exceptions\StorageDestinationException::class);
});

it('maps sftp exceptions to user friendly messages', function () {
    $mapper = app(SftpErrorMapper::class);

    expect($mapper->map(new UnableToLoadPrivateKey()))
        ->toContain('Invalid private key format');

    expect($mapper->map(UnableToAuthenticate::withPassword()))
        ->toContain('Invalid username/password');

    expect($mapper->map(UnableToConnectToSftpHost::atHostname('10.0.0.1')))
        ->toContain('Host unreachable');
});

it('resolves auth method enum from config', function () {
    expect(SftpAuthenticationMethod::Password->value)->toBe('password');
    expect(SftpAuthenticationMethod::PrivateKey->value)->toBe('private_key');
});
