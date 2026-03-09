<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Tests\Helpers;

use Saloon\Http\Faking\MockClient;
use Ursamajeur\CloudCodePA\Auth\CloudCodeAuthenticator;
use Ursamajeur\CloudCodePA\Config\ModelRegistry;
use Ursamajeur\CloudCodePA\Contracts\CredentialStoreInterface;
use Ursamajeur\CloudCodePA\Gateway\CloudCodeGateway;
use Ursamajeur\CloudCodePA\Parsing\RequestBuilder;
use Ursamajeur\CloudCodePA\Parsing\ResponseMapper;
use Ursamajeur\CloudCodePA\Transport\GeminiCLI\GeminiCLIConnector;

final class GatewayFactory
{
    /**
     * Create a CloudCodeGateway wired to a Saloon MockClient.
     *
     * @param  array<string, string>  $models  Model alias → bare name map
     */
    public static function make(
        MockClient $mockClient,
        array $models = ['gemini-2.0-flash' => 'gemini-2.0-flash'],
        bool $debug = false,
    ): CloudCodeGateway {
        $credentialStore = \Mockery::mock(CredentialStoreInterface::class);
        $credentialStore->shouldReceive('isExpired')->andReturn(false);
        $credentialStore->shouldReceive('getAccessToken')->andReturn('test-token');

        $authenticator = new CloudCodeAuthenticator(
            credentialStore: $credentialStore,
            clientId: 'test-id',
            clientSecret: 'test-secret',
        );

        $connector = new GeminiCLIConnector(
            baseUrl: 'https://cloudcode-pa.googleapis.com/v1internal',
            cloudCodeAuth: $authenticator,
            debug: $debug,
        );

        $connector->withMockClient($mockClient);

        return new CloudCodeGateway(
            connector: $connector,
            requestBuilder: new RequestBuilder(modelRegistry: new ModelRegistry($models)),
            responseMapper: new ResponseMapper,
        );
    }
}
