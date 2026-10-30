<?php

it('sends visitors to the dashboard', function () {
    $this->get('/')->assertRedirect('/dashboard');
});

it('asks guests to log in', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});
