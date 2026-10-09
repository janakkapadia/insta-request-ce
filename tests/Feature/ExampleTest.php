<?php

test('returns a redirect to login response', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect('/login');
});

test('marketing pages redirect to login', function (string $route) {
    $response = $this->get(route($route));

    $response->assertRedirect('/login');
})->with([
    'postman-alternative',
    'api-monitoring',
    'api-collaboration',
    'request-builder',
    'realtime-api-workspace',
]);
