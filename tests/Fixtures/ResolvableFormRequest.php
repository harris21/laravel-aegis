<?php

declare(strict_types=1);

namespace HarrisRafto\Aegis\Tests\Fixtures;

use HarrisRafto\Aegis\Concerns\ResolvesValueObjects;
use Illuminate\Foundation\Http\FormRequest;

class ResolvableFormRequest extends FormRequest
{
    use ResolvesValueObjects;
}
