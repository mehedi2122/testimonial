<?php

namespace App\Enums;

/**
 * Why `mode` is one enum (decision log + §3.3):
 * Two booleans (enabled, required) allow four states; one is nonsense:
 * "hidden but mandatory" — an unsubmittable form. One enum makes the
 * fourth row unrepresentable rather than merely discouraged.
 */
enum SpaceFieldMode: string
{
    case Off = 'off';
    case Optional = 'optional';
    case Required = 'required';
}
