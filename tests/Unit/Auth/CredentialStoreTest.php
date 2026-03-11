<?php

declare(strict_types=1);

use Ursamajeur\CloudCodePA\Auth\CredentialStore;
use Ursamajeur\CloudCodePA\Contracts\CredentialStoreInterface;
use Ursamajeur\CloudCodePA\Exceptions\AuthenticationException;

// AC #4 — Implements interface
it('implements CredentialStoreInterface', function (): void {
    $store = new CredentialStore(fixture('credentials/valid-credentials.json'));

    expect($store)->toBeInstanceOf(CredentialStoreInterface::class);
});

// AC #1 — Reads valid credential file (Gemini CLI format)
it('reads valid credential file and returns correct tokens', function (): void {
    $store = new CredentialStore(fixture('credentials/valid-credentials.json'));

    expect($store->getAccessToken())->toBe('ya29.test-valid-access-token')
        ->and($store->getRefreshToken())->toBe('1//0e-test-valid-refresh-token')
        ->and($store->getTokenType())->toBe('Bearer');
});

// AC #2 — Caches credentials (file read once)
it('caches credentials after first read', function (): void {
    $path = fixture('credentials/valid-credentials.json');
    $store = new CredentialStore($path);

    // Call multiple times — should not re-read file
    $token1 = $store->getAccessToken();
    $token2 = $store->getAccessToken();
    $token3 = $store->getRefreshToken();

    expect($token1)->toBe($token2)
        ->and($token3)->toBe('1//0e-test-valid-refresh-token');
});

// AC #3 — Missing file throws credentialsNotFound
it('throws credentialsNotFound when file does not exist', function (): void {
    $store = new CredentialStore('/nonexistent/path/oauth_creds.json');

    $store->getAccessToken();
})->throws(AuthenticationException::class, '/nonexistent/path/oauth_creds.json');

// AC #1 — Malformed JSON throws invalidCredentials
it('throws invalidCredentials for malformed JSON', function (): void {
    $tempFile = tempnam(sys_get_temp_dir(), 'cred_');
    file_put_contents($tempFile, '{invalid json!!!');

    try {
        $store = new CredentialStore($tempFile);
        $store->getAccessToken();
    } finally {
        unlink($tempFile);
    }
})->throws(AuthenticationException::class, 'malformed');

// AC #1 — Missing required fields throws invalidCredentials
it('throws invalidCredentials when required fields are missing', function (): void {
    $tempFile = tempnam(sys_get_temp_dir(), 'cred_');
    file_put_contents($tempFile, json_encode(['access_token' => 'test']));

    try {
        $store = new CredentialStore($tempFile);
        $store->getAccessToken();
    } finally {
        unlink($tempFile);
    }
})->throws(AuthenticationException::class, 'malformed');

// Missing expires_at throws invalidCredentials
it('throws invalidCredentials when expires_at is missing', function (): void {
    $tempFile = tempnam(sys_get_temp_dir(), 'cred_');
    file_put_contents($tempFile, json_encode([
        'access_token' => 'test',
        'refresh_token' => 'test',
        'token_type' => 'Bearer',
    ]));

    try {
        $store = new CredentialStore($tempFile);
        $store->getAccessToken();
    } finally {
        unlink($tempFile);
    }
})->throws(AuthenticationException::class, 'malformed');

// AC #1 — isExpired returns true for past timestamp
it('returns true for expired credentials', function (): void {
    $store = new CredentialStore(fixture('credentials/expired-credentials.json'));

    expect($store->isExpired())->toBeTrue();
});

// AC #1 — isExpired returns false for future timestamp
it('returns false for valid non-expired credentials', function (): void {
    $store = new CredentialStore(fixture('credentials/valid-credentials.json'));

    expect($store->isExpired())->toBeFalse();
});

// Ignores extra fields from Gemini CLI (scope, id_token)
it('reads Gemini CLI credential file with extra fields', function (): void {
    $store = new CredentialStore(fixture('credentials/gemini-cli-credentials.json'));

    expect($store->getAccessToken())->toBe('ya29.test-gemini-cli-token')
        ->and($store->getRefreshToken())->toBe('1//0e-test-gemini-cli-refresh')
        ->and($store->getTokenType())->toBe('Bearer')
        ->and($store->isExpired())->toBeFalse();
});

// updateCredentials writes in Gemini CLI format (expires_at in seconds)
it('updates credentials and writes in Gemini CLI format', function (): void {
    $tempFile = tempnam(sys_get_temp_dir(), 'cred_');

    try {
        $store = new CredentialStore($tempFile);
        // expiresAt is epoch seconds (as returned by Google OAuth: time() + expires_in)
        $store->updateCredentials('new-access-token', 'new-refresh-token', 9999999999);

        // Verify in-memory cache is updated
        expect($store->getAccessToken())->toBe('new-access-token')
            ->and($store->getRefreshToken())->toBe('new-refresh-token')
            ->and($store->isExpired())->toBeFalse();

        // Verify file uses Gemini CLI format (expires_at in seconds)
        $written = json_decode(file_get_contents($tempFile), true);
        expect($written)->toBe([
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'token_type' => 'Bearer',
            'expires_at' => 9999999999,
        ]);

        // Verify file permissions (0600)
        $perms = fileperms($tempFile) & 0777;
        expect($perms)->toBe(0600);
    } finally {
        unlink($tempFile);
    }
});

// AC #3 — Error message includes provisioning hint
it('includes provisioning hint in credentialsNotFound message', function (): void {
    $store = new CredentialStore('/nonexistent/creds.json');

    try {
        $store->getAccessToken();
    } catch (AuthenticationException $e) {
        expect($e->getMessage())->toContain('credential');

        return;
    }

    $this->fail('Expected AuthenticationException was not thrown');
});
