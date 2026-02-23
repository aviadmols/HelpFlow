<?php

use App\Services\Chat\TemplateRenderer;

test('render replaces placeholders with variables', function () {
    $renderer = new TemplateRenderer;
    $result = $renderer->render('Hello {{name}}, your code is {{code}}.', ['name' => 'John', 'code' => 'ABC123']);
    expect($result)->toBe('Hello John, your code is ABC123.');
});

test('render uses empty string for missing variables', function () {
    $renderer = new TemplateRenderer;
    $result = $renderer->render('Hello {{name}}.', []);
    expect($result)->toBe('Hello .');
});

test('render strips remaining placeholders', function () {
    $renderer = new TemplateRenderer;
    $result = $renderer->render('Hello {{name}} and {{unknown}}.', ['name' => 'Jane']);
    expect($result)->toBe('Hello Jane and .');
});
