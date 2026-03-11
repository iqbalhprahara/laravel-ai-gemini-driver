<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Config;

enum RpcType: string
{
    case GenerateContent = 'generateContent';
    case GenerateChat = 'generateChat';
}
