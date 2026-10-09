<?php

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use LeanCaptain\LaraEmail\Rules\UsableEmailDomain;

/** @return list<string> */
function emailDomainErrors(UsableEmailDomain $rule, mixed $email): array
{
    $errors = [];
    $rule->validate('email', $email, function (string $message) use (&$errors): void {
        $errors[] = $message;
    });

    return $errors;
}

function emailValidator(): Factory
{
    return new Factory(new Translator(new ArrayLoader, 'en'));
}
