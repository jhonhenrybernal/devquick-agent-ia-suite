<?php

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});

test('the home page generates secure asset urls behind a trusted proxy', function () {
    $response = $this
        ->withServerVariables([
            'HTTP_HOST' => 'localhost:8000',
            'HTTP_X_FORWARDED_HOST' => 'lips-midlands-largely-uniprotkb.trycloudflare.com',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ])
        ->get('/');

    $response->assertOk();
    $response->assertSee('https://lips-midlands-largely-uniprotkb.trycloudflare.com/build/assets', false);
    $response->assertDontSee('http://localhost:8000/build/assets', false);
});
