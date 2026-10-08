<?php

namespace App\Http\Concerns;

trait EscapesLike
{
    protected function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
