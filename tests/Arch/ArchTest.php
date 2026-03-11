<?php

declare(strict_types=1);

arch('all src classes use strict types')
    ->expect('Ursamajeur\CloudCodePA')
    ->toUseStrictTypes();

arch('all src classes are final')
    ->expect('Ursamajeur\CloudCodePA')
    ->toBeFinal()
    ->ignoring('Ursamajeur\CloudCodePA\Exceptions')
    ->ignoring('Ursamajeur\CloudCodePA\Contracts')
    ->ignoring('Ursamajeur\CloudCodePA\Config\RpcType');

arch('src classes do not use env() directly')
    ->expect('Ursamajeur\CloudCodePA')
    ->not->toUse(['env']);

arch('src classes do not use debugging functions')
    ->expect('Ursamajeur\CloudCodePA')
    ->not->toUse(['dd', 'dump', 'ray', 'var_dump', 'print_r']);

arch('exceptions extend CloudCodeException')
    ->expect('Ursamajeur\CloudCodePA\Exceptions')
    ->toExtend('Ursamajeur\CloudCodePA\Exceptions\CloudCodeException')
    ->ignoring('Ursamajeur\CloudCodePA\Exceptions\CloudCodeException');

arch('config classes do not depend on transport')
    ->expect('Ursamajeur\CloudCodePA\Config')
    ->not->toUse('Ursamajeur\CloudCodePA\Transport');

arch('src classes do not use Laravel Http facade — use Saloon')
    ->expect('Ursamajeur\CloudCodePA')
    ->not->toUse(['Illuminate\Support\Facades\Http']);
