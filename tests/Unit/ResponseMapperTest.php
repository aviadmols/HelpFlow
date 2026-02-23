<?php

use App\Services\Chat\ResponseMapper;

test('map extracts by dot path', function () {
    $mapper = new ResponseMapper;
    $config = ['discount_code' => 'discount.code'];
    $body = ['discount' => ['code' => 'SAVE25']];
    $result = $mapper->map($config, $body);
    expect($result)->toBe(['discount_code' => 'SAVE25']);
});

test('map returns null for missing path', function () {
    $mapper = new ResponseMapper;
    $config = ['missing' => 'foo.bar'];
    $result = $mapper->map($config, []);
    expect($result)->toBe(['missing' => null]);
});
