<?php

test('example', function () {

    $response = $this->post('/api/register', [
        'name' => 'Ahmed Mostafa',
        'email' => 'lofylofy56@gmail.com',
        'password' => 'lofylofy56',
        'password_confirmation' => 'lofylofy56',
        'user_type' => 'admin',
    ]);

    $response->assertStatus(201);
});
