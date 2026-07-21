<?php

test('returns a redirect to login response', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect('/login');
});
