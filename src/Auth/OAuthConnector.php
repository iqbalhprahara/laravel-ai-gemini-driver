<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Auth;

use Saloon\Http\Connector;

final class OAuthConnector extends Connector
{
    public function resolveBaseUrl(): string
    {
        return 'https://oauth2.googleapis.com';
    }
}
