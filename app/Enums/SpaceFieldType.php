<?php

namespace App\Enums;

enum SpaceFieldType: string
{
    case Text = 'text';
    case Url = 'url';
    case Email = 'email';
    case Image = 'image';
    case Number = 'number';
}
